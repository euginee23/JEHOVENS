<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Contracts\Reservation;
use App\Models\RoomBooking;
use App\Notifications\ReservationPaymentFailed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Turning what PayMongo says into what the resort's books say.
 *
 * Two things report a payment: the webhook, and the guest arriving back on the success
 * URL. Either may be first — a webhook can beat the browser, or arrive minutes later —
 * so both call the same methods here and whichever is second does nothing. Getting that
 * wrong means two confirmation emails and a payment recorded twice.
 */
class PayMongoPayments
{
    /**
     * The reservation types the resort takes payments for, as the checkout metadata and
     * the payment routes name them.
     */
    public const TYPES = ['hall', 'room', 'catering'];

    /**
     * Whether this is a reservation type the resort takes payments for.
     */
    public static function isKnownType(string $type): bool
    {
        return in_array($type, self::TYPES, strict: true);
    }

    /**
     * A query over the table a reservation type lives in.
     *
     * Spelled out as a match over the three concrete models rather than resolved from a
     * class name held in a variable: this way what comes back is known to be one of the
     * three, and therefore known to be a Reservation.
     *
     * @return Builder<Booking>|Builder<RoomBooking>|Builder<CateringOrder>
     */
    public static function query(string $type): Builder
    {
        return match ($type) {
            'hall' => Booking::query(),
            'room' => RoomBooking::query(),
            'catering' => CateringOrder::query(),
            default => throw new InvalidArgumentException("Unknown reservation type [{$type}]."),
        };
    }

    /**
     * Find a reservation by its type and reference.
     */
    public static function find(string $type, string $reference): ?Reservation
    {
        if (! self::isKnownType($type)) {
            return null;
        }

        return self::query($type)->where('reference', $reference)->first();
    }

    /**
     * Record a paid checkout and confirm the reservation behind it.
     *
     * Returns whether this call was the one that did the work, so a caller can tell a
     * first delivery from a replay.
     */
    public static function markPaid(string $type, string $reference, CheckoutSession $session): bool
    {
        if (! self::isKnownType($type)) {
            return false;
        }

        $reservation = DB::transaction(function () use ($type, $reference, $session) {
            // Locked for the length of the check-and-write, so a webhook and a returning
            // guest arriving together cannot both decide they were first.
            $reservation = self::query($type)->where('reference', $reference)->lockForUpdate()->first();

            if (! $reservation || $reservation->paymentStatus() === PaymentStatus::Paid) {
                return null;
            }

            $expected = $reservation->amountPaid();
            $received = $session->paidAmount ?? 0;

            // Short payments are left Pending for staff to look at rather than confirmed:
            // the guest has parted with money, so cancelling it outright would be worse.
            if ($received < $expected) {
                Log::warning('PayMongo reported a short payment.', [
                    'reference' => $reference,
                    'expected' => $expected,
                    'received' => $received,
                    'session' => $session->id,
                ]);

                return null;
            }

            $reservation->recordPayment(PaymentStatus::Paid, [
                'payment_provider' => 'paymongo',
                'payment_session_id' => $session->id,
                'payment_intent_id' => $session->paymentIntentId,
                'payment_reference' => $session->paymentId,
                'payment_method' => $session->paymentMethod,
                'paid_amount' => $received,
                'paid_at' => now(),
                'payment_expires_at' => null,
            ]);

            // The guest gets one clear "you have paid, you are confirmed" email below
            // rather than that plus a separate status-change notice for the same event.
            $reservation->transitionTo(BookingStatus::Confirmed, notify: false);

            if ($reservation->balanceRemaining() === 0) {
                $reservation->settleBalance();
            }

            return $reservation;
        });

        if (! $reservation) {
            return false;
        }

        // Sent after the transaction commits, so a guest never reads a receipt for a
        // booking a rolled-back write means the resort has no record of.
        $reservation->sendPlacementNotifications();

        return true;
    }

    /**
     * Record that a payment attempt failed, leaving the booking for the guest to retry.
     *
     * The reservation stays Pending and keeps its dates until the hold expires — a guest
     * whose card was declined usually tries another one a moment later.
     */
    public static function markFailed(string $type, string $reference): bool
    {
        $reservation = self::find($type, $reference);

        if (! $reservation || $reservation->paymentStatus() === PaymentStatus::Paid) {
            return false;
        }

        $reservation->recordPayment(PaymentStatus::Failed);

        $reservation->notifyGuestOf(new ReservationPaymentFailed($reservation->toSummary()));

        return true;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PayMongoException;
use App\Support\PayMongo;
use App\Support\PayMongoPayments;
use Illuminate\Http\RedirectResponse;

/**
 * Where the guest lands when they come back from PayMongo.
 *
 * Both of these are reached by a signed URL. Without a signature the booking reference —
 * six characters — would be enough for a stranger to cancel somebody else's booking.
 */
class PaymentReturnController extends Controller
{
    /**
     * The guest finished checkout.
     *
     * The redirect is not taken as proof of payment: it only says the guest got back here.
     * PayMongo is asked what actually happened, and the answer goes through the same
     * handler the webhook uses — so whichever arrives second is a no-op.
     */
    public function success(string $type, string $reference): RedirectResponse
    {
        $reservation = PayMongoPayments::find($type, $reference);

        if (! $reservation) {
            return $this->backTo($type)->with('payment_error', __('We could not find that booking.'));
        }

        if ($reservation->paymentStatus() !== PaymentStatus::Paid && $reservation->paymentSessionId()) {
            try {
                $session = PayMongo::make()->retrieveCheckoutSession($reservation->paymentSessionId());

                if ($session->isPaid) {
                    PayMongoPayments::markPaid($type, $reference, $session);
                }
            } catch (PayMongoException $e) {
                // The webhook is still coming. Show the guest what the resort knows now
                // rather than an error for something that is probably fine.
                report($e);
            }
        }

        // Passed in the session rather than the query string: a reference in a URL would
        // turn this page into a way to browse other people's bookings by guessing.
        return $this->backTo($type)->with('reservation_reference', $reference);
    }

    /**
     * The guest backed out of checkout.
     *
     * Their dates go back on sale immediately rather than waiting for the sweeper — they
     * have said they are not paying, so there is nothing left to hold them for.
     */
    public function cancel(string $type, string $reference): RedirectResponse
    {
        $reservation = PayMongoPayments::find($type, $reference);

        if ($reservation && $reservation->paymentStatus() !== PaymentStatus::Paid) {
            $reservation->recordPayment(PaymentStatus::Expired);
            $reservation->transitionTo(BookingStatus::Cancelled, notify: false);
        }

        return $this->backTo($type)
            ->with('payment_error', __('Payment cancelled — your dates have been released.'));
    }

    /**
     * The booking page this reservation type belongs to.
     */
    protected function backTo(string $type): RedirectResponse
    {
        return redirect()->route(match ($type) {
            'room' => 'booking.rooms',
            'catering' => 'booking.catering',
            default => 'booking.function-hall',
        });
    }
}

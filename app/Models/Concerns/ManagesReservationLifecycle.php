<?php

namespace App\Models\Concerns;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Notifications\NewReservationAlert;
use App\Notifications\ReservationBalanceSettled;
use App\Notifications\ReservationReceived;
use App\Notifications\ReservationStatusChanged;
use App\Support\ReservationSummary;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Status, balance and email handling shared by hall bookings, room bookings and
 * catering orders.
 *
 * The three tables disagree on one column name — halls and catering call the amount
 * received `downpayment`, rooms call it `amount_paid` — so the using model names it.
 *
 * This is also the single choke point for reservation email: every status change and
 * every settled balance passes through here, so no route can move a booking on without
 * telling the guest.
 */
trait ManagesReservationLifecycle
{
    /**
     * The column holding what the guest has already paid.
     */
    abstract public function amountPaidColumn(): string;

    /**
     * Whether this reservation is over and done with.
     *
     * Provided by {@see HasReservationDates}, and sharpened by the types that know an end
     * time rather than only an end date.
     */
    abstract public function hasFinished(): bool;

    /**
     * What the guest has already paid.
     */
    public function amountPaid(): int
    {
        return (int) $this->{$this->amountPaidColumn()};
    }

    /**
     * The code the guest and the resort both know this reservation by.
     */
    public function reference(): string
    {
        return (string) $this->reference;
    }

    /**
     * What the resort still expects to be paid.
     */
    public function balanceRemaining(): int
    {
        return (int) $this->balance;
    }

    /**
     * Where this reservation's money has got to.
     */
    public function paymentStatus(): PaymentStatus
    {
        return $this->payment_status;
    }

    /**
     * The checkout session the guest was last sent to, if any.
     */
    public function paymentSessionId(): ?string
    {
        return $this->payment_session_id;
    }

    /**
     * Write down what the payment gateway said.
     *
     * The payment columns are deliberately not fillable from a form — only the gateway
     * and the sweeper have any business setting them — so this is the way in.
     *
     * @param  array<string, mixed>  $details  the other payment columns to set alongside
     */
    public function recordPayment(PaymentStatus $status, array $details = []): void
    {
        $this->forceFill([...$details, 'payment_status' => $status])->save();
    }

    /**
     * Whether the guest still owes the resort money.
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->balance > 0 && $this->balance_settled_at === null;
    }

    /**
     * Move the booking to a new status, if that move is allowed from where it is now.
     *
     * Returns false rather than throwing so the caller can show a message: an admin
     * clicking a stale button should not see an exception. A rejected move sends no
     * email, since as far as the guest is concerned nothing happened.
     */
    public function transitionTo(BookingStatus $status, bool $notify = true): bool
    {
        if (! in_array($status, $this->status->transitions(), strict: true)) {
            return false;
        }

        // Completing a reservation puts its days back on sale, so it cannot happen while
        // the guests may still be here. Marking it on the last day itself is fine — that
        // is exactly the case the resort asked for, a room freed up the evening it is
        // vacated — but a week early would sell the venue out from under an event.
        if ($status === BookingStatus::Completed && ! $this->hasFinished()) {
            return false;
        }

        // Completing a reservation implies the balance came in with it.
        $settledNow = $status === BookingStatus::Completed && $this->hasOutstandingBalance();

        if ($settledNow) {
            $this->balance_settled_at = now();
        }

        $this->status = $status;

        if (! $this->save()) {
            return false;
        }

        // `$notify` is off when the caller is already telling the guest something better
        // about the same event — a payment confirmation says far more than "now confirmed".
        if ($notify) {
            $this->notifyGuest(new ReservationStatusChanged($this->toSummary()));
        }

        // Money changing hands is worth its own receipt. Settling the balance through
        // `settleBalance()` sends one, and completing a booking that still owed money
        // used to be the one path that quietly skipped it.
        if ($settledNow && $notify) {
            $this->notifyGuest(new ReservationBalanceSettled($this->toSummary()));
        }

        return true;
    }

    /**
     * Record that the remaining balance has been collected.
     */
    public function settleBalance(): bool
    {
        if (! $this->hasOutstandingBalance()) {
            return false;
        }

        $this->balance_settled_at = now();

        if (! $this->save()) {
            return false;
        }

        $this->notifyGuest(new ReservationBalanceSettled($this->toSummary()));

        return true;
    }

    /**
     * Send the receipt to the guest and the alert to the resort, once the booking has
     * been placed.
     *
     * Called from the booking pages rather than from a model event: nothing else in this
     * application uses model events, and a factory building test data has no business
     * sending mail.
     */
    public function sendPlacementNotifications(): void
    {
        $summary = $this->toSummary();

        $this->notifyGuest(new ReservationReceived($summary));

        Notifications::route('mail', config('resort.notifications.admin_email'))
            ->notify(new NewReservationAlert($summary));
    }

    /**
     * This reservation flattened into the shape the emails and admin lists read.
     */
    public function toSummary(): ReservationSummary
    {
        return ReservationSummary::from($this);
    }

    /**
     * Tell the guest something about their reservation.
     *
     * The public way in, for the payment handling that lives outside this trait. Anything
     * the resort sends a guest still goes through here, so `guest_email` is checked once.
     */
    public function notifyGuestOf(Notification $notification): void
    {
        $this->notifyGuest($notification);
    }

    /**
     * Send a notification to whoever made the booking.
     *
     * Addressed rather than sent to a User: most guests book without an account, so
     * `user_id` is usually null and `guest_email` is the only way to reach them.
     */
    protected function notifyGuest(Notification $notification): void
    {
        if (blank($this->guest_email)) {
            return;
        }

        Notifications::route('mail', $this->guest_email)->notify($notification);
    }
}

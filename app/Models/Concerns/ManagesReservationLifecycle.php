<?php

namespace App\Models\Concerns;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\NewReservationAlert;
use App\Notifications\ReservationBalanceSettled;
use App\Notifications\ReservationNotification;
use App\Notifications\ReservationReceived;
use App\Notifications\ReservationStatusChanged;
use App\Support\DeliverMail;
use App\Support\ReservationSummary;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
     * The money actually received against this reservation, oldest first.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable')->orderBy('received_at')->orderBy('id');
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

        // Confirming is the moment the downpayment counts as verified, whether PayMongo
        // or a member of staff did it.
        if ($status === BookingStatus::Confirmed) {
            $this->recordDownpaymentReceived();
        }

        if ($settledNow) {
            $this->recordBalanceReceived();
        }

        // `$notify` is off when the caller is already telling the guest something better
        // about the same event — a payment confirmation says far more than "now confirmed".
        // Completing a booking that still owed money settles it too, but the completed
        // email already says it is paid in full, so no separate balance receipt follows.
        if ($notify) {
            $this->notifyGuest(new ReservationStatusChanged($this->toSummary()));
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

        $this->recordBalanceReceived();

        $this->notifyGuest(new ReservationBalanceSettled($this->toSummary()));

        return true;
    }

    /**
     * Write the downpayment into the payments ledger, once.
     *
     * Once, because the guest only paid the one downpayment. The status buttons no longer
     * lead back to Confirmed, but a gateway can still deliver the same payment twice. A PayMongo payment
     * carries its own method, reference and time; one confirmed by hand is dated now and
     * credited to whoever is signed in.
     */
    protected function recordDownpaymentReceived(): void
    {
        $amount = $this->amountPaid();

        if ($amount <= 0 || $this->payments()->where('kind', PaymentKind::Downpayment)->exists()) {
            return;
        }

        $this->payments()->create([
            'kind' => PaymentKind::Downpayment,
            'amount' => $amount,
            'method' => $this->payment_method,
            'reference' => $this->payment_reference,
            // `paid_at` is only ever set by PayMongo, so a downpayment carrying it was
            // verified by the gateway rather than by whoever happens to be signed in.
            'recorded_by' => $this->paid_at === null ? $this->signedInStaffId() : null,
            'received_at' => $this->paid_at ?? now(),
        ]);
    }

    /**
     * Write the settled balance into the payments ledger.
     */
    protected function recordBalanceReceived(): void
    {
        if ($this->balanceRemaining() <= 0) {
            return;
        }

        $this->payments()->create([
            'kind' => PaymentKind::Balance,
            'amount' => $this->balanceRemaining(),
            'recorded_by' => $this->signedInStaffId(),
            'received_at' => $this->balance_settled_at ?? now(),
        ]);
    }

    /**
     * Whoever is signed in to the admin, to credit with a payment they recorded.
     */
    protected function signedInStaffId(): ?int
    {
        $id = auth()->id();

        return is_int($id) ? $id : null;
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

        app(DeliverMail::class)(
            (string) config('resort.notifications.admin_email'),
            new NewReservationAlert($summary),
        );
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
    public function notifyGuestOf(ReservationNotification $notification): void
    {
        $this->notifyGuest($notification);
    }

    /**
     * Send a notification to whoever made the booking.
     *
     * Addressed rather than sent to a User: most guests book without an account, so
     * `user_id` is usually null and `guest_email` is the only way to reach them.
     */
    protected function notifyGuest(ReservationNotification $notification): void
    {
        if (blank($this->guest_email)) {
            return;
        }

        app(DeliverMail::class)($this->guest_email, $notification);
    }
}

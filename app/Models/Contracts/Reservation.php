<?php

namespace App\Models\Contracts;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasReservationDates;
use App\Models\Concerns\ManagesReservationLifecycle;
use App\Support\ReservationSummary;
use Illuminate\Notifications\Notification;

/**
 * Something a guest has booked and paid for — a hall booking, a room booking, or a
 * catering order.
 *
 * The three live in their own tables with their own columns, but everything to do with
 * status, money and email treats them alike. Payment handling, the expiry sweeper and the
 * checkout redirect all work against this rather than against one of the three, so none
 * of them has to branch on which table it is holding.
 *
 * The behaviour comes from {@see ManagesReservationLifecycle} and
 * {@see HasReservationDates}; this only states what may be relied on.
 */
interface Reservation extends Schedulable
{
    /**
     * The code the guest and the resort both know this reservation by.
     */
    public function reference(): string;

    /**
     * What the guest has already paid.
     */
    public function amountPaid(): int;

    /**
     * What the resort still expects to be paid.
     */
    public function balanceRemaining(): int;

    /**
     * Where this reservation's money has got to.
     */
    public function paymentStatus(): PaymentStatus;

    /**
     * The checkout session the guest was last sent to, if any.
     */
    public function paymentSessionId(): ?string;

    /**
     * Write down what the payment gateway said.
     *
     * @param  array<string, mixed>  $details  the other payment columns to set alongside
     */
    public function recordPayment(PaymentStatus $status, array $details = []): void;

    /**
     * Whether the guest still owes the resort money.
     */
    public function hasOutstandingBalance(): bool;

    /**
     * Move to a new status, if that move is allowed from where this is now.
     */
    public function transitionTo(BookingStatus $status, bool $notify = true): bool;

    /**
     * Record that the remaining balance has been collected.
     */
    public function settleBalance(): bool;

    /**
     * Send the guest their receipt and the resort its alert.
     */
    public function sendPlacementNotifications(): void;

    /**
     * Tell the guest something about this reservation.
     */
    public function notifyGuestOf(Notification $notification): void;

    /**
     * This reservation flattened into the shape the emails and admin lists read.
     */
    public function toSummary(): ReservationSummary;
}

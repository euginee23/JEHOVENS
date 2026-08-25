<?php

namespace App\Models\Concerns;

use App\Enums\BookingStatus;

/**
 * Status and balance handling shared by hall bookings and room bookings.
 *
 * The two tables disagree on one column name — halls call the amount received
 * `downpayment`, rooms call it `amount_paid` — so the using model names it.
 */
trait ManagesReservationLifecycle
{
    /**
     * The column holding what the guest has already paid.
     */
    abstract public function amountPaidColumn(): string;

    /**
     * What the guest has already paid.
     */
    public function amountPaid(): int
    {
        return (int) $this->{$this->amountPaidColumn()};
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
     * clicking a stale button should not see an exception.
     */
    public function transitionTo(BookingStatus $status): bool
    {
        if (! in_array($status, $this->status->transitions(), strict: true)) {
            return false;
        }

        // Completing a reservation implies the balance came in with it.
        if ($status === BookingStatus::Completed && $this->hasOutstandingBalance()) {
            $this->balance_settled_at = now();
        }

        $this->status = $status;

        return $this->save();
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

        return $this->save();
    }
}

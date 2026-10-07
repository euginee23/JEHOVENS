<?php

namespace App\Enums;

use App\Models\Concerns\ManagesReservationLifecycle;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for payment'),
            self::Confirmed => __('Booked'),
            self::Completed => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * A compact label for table cells and filter chips.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Pending => __('Unpaid'),
            self::Confirmed => __('Booked'),
            self::Completed => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * What this status means, in a sentence staff can read without asking.
     *
     * One plain sentence each: the resort's staff read the status as a step in a line
     * — Unpaid, then Booked, then Done — and needed each to say where that line stands.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => __('The guest has not paid the downpayment yet. It becomes Booked once the payment comes in.'),
            self::Confirmed => __('The downpayment is paid and the dates are held for the guest. Mark it done once they have left.'),
            self::Completed => __('The guest has come and gone. This is final. It is kept in History for your records.'),
            self::Cancelled => __('The booking will not go ahead and the dates are free again. This is final. It is kept in History for your records.'),
        };
    }

    /**
     * The label for a button that moves a booking to this status.
     *
     * Nothing moves a booking back to Unpaid any more; that arm is only here because the
     * match has to cover every case.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Pending => __('Mark unpaid'),
            self::Confirmed => __('Confirm payment'),
            self::Completed => __('Mark as done'),
            self::Cancelled => __('Cancel booking'),
        };
    }

    /**
     * Tailwind classes for this status' pill.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Confirmed => 'bg-brand-50 text-brand-700',
            self::Completed => 'bg-emerald-50 text-emerald-700',
            self::Cancelled => 'bg-zinc-100 text-zinc-500',
        };
    }

    /**
     * Statuses that still hold the venue for the days booked.
     *
     * Completed is deliberately absent. The resort marks a booking completed once the
     * guests have gone, and expects those days to go back on sale — holding a finished
     * event's days against a new booking serves nobody. Nothing is freed early: a
     * reservation can only be completed once its last day has arrived, which
     * {@see ManagesReservationLifecycle::transitionTo()} enforces.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * Statuses still in progress, which the bookings page lists as Active.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * Statuses that are over and done with, which the bookings page lists as History.
     *
     * @return array<int, self>
     */
    public static function history(): array
    {
        return [self::Completed, self::Cancelled];
    }

    /**
     * Whether a booking in this status can no longer be changed.
     */
    public function isFinal(): bool
    {
        return in_array($this, self::history(), strict: true);
    }

    /**
     * Statuses an admin may move this booking to.
     *
     * The line only runs one way: Unpaid, Booked, Done, with Cancelled open until then.
     * Done and Cancelled used to have a way back — Reopen and Reinstate — and staff read
     * the result as the status flipping back and forth, so both are final now.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }
}

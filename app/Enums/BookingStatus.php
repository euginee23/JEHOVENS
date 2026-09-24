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
            self::Pending => __('Awaiting payment confirmation'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * A compact label for table cells and filter chips.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
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
     * Statuses an admin may move this booking to.
     *
     * Completed can be walked back to Confirmed, which matters far more now that
     * completing a booking puts its days back on sale.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed => [self::Confirmed],
            self::Cancelled => [self::Pending],
        };
    }
}

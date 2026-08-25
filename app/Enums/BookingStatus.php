<?php

namespace App\Enums;

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
     * Statuses that still hold the hall for the requested slot.
     *
     * Completed stays here: a finished event still occupied its slot, so it must keep
     * blocking that time from being double-booked after the fact.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed, self::Completed];
    }

    /**
     * Statuses an admin may move this booking to.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [self::Pending],
        };
    }
}

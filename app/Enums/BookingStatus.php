<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * The human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment confirmation'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Statuses that still hold the hall for the requested slot.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }
}

<?php

namespace App\Enums;

/**
 * Where a reservation's money has got to.
 *
 * Kept apart from {@see BookingStatus}, which tracks the reservation itself. The two
 * answer different questions: a booking whose checkout is still open and one whose card
 * was declined are both Pending, but the resort needs to tell them apart.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Awaiting = 'awaiting';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * How this reads to staff, spelled out.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('No payment started'),
            self::Awaiting => __('Waiting for payment'),
            self::Paid => __('Paid'),
            self::Failed => __('Payment failed'),
            self::Expired => __('Payment window expired'),
        };
    }

    /**
     * The short form, for a table cell.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Awaiting => __('Awaiting'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
        };
    }

    /**
     * Pill classes, matching how BookingStatus renders.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-zinc-100 text-zinc-500',
            self::Awaiting => 'bg-amber-50 text-amber-700',
            self::Paid => 'bg-emerald-50 text-emerald-700',
            self::Failed => 'bg-red-50 text-red-700',
            self::Expired => 'bg-zinc-100 text-zinc-500',
        };
    }
}

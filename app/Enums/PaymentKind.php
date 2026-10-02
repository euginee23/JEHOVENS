<?php

namespace App\Enums;

/**
 * Which part of a reservation's price a received payment covers.
 */
enum PaymentKind: string
{
    case Downpayment = 'downpayment';
    case Balance = 'balance';

    /**
     * How this reads to staff.
     */
    public function label(): string
    {
        return match ($this) {
            self::Downpayment => __('Downpayment'),
            self::Balance => __('Balance'),
        };
    }
}

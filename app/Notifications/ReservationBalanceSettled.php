<?php

namespace App\Notifications;

/**
 * The receipt a guest gets once an admin records their remaining balance as collected.
 */
class ReservationBalanceSettled extends ReservationNotification
{
    protected function subject(): string
    {
        return __('Balance received — :reference', ['reference' => $this->reservation->reference]);
    }

    protected function template(): string
    {
        return 'mail.reservation-balance-settled';
    }
}

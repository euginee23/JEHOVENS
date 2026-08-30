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

    protected function heading(): string
    {
        return __('Your balance is settled');
    }

    protected function intro(): string
    {
        return __('We have received the rest of your payment. Nothing further is owed on this booking.');
    }
}

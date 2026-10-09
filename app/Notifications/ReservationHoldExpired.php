<?php

namespace App\Notifications;

/**
 * Told to a guest whose dates were released because payment never arrived.
 *
 * Sent by the sweeper rather than by anything the guest did, so it has to explain itself:
 * from their side nothing happened at all, and then the booking was gone.
 */
class ReservationHoldExpired extends ReservationNotification
{
    protected function subject(): string
    {
        return __('Your booking was not completed — :reference', ['reference' => $this->reservation->reference]);
    }

    protected function template(): string
    {
        return 'mail.reservation-hold-expired';
    }
}

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

    protected function heading(): string
    {
        return __('We have released your dates');
    }

    protected function intro(): string
    {
        return __('We held the dates below while you paid, but the payment was never completed, so they have gone back on sale. Nothing has been charged.');
    }

    protected function outro(): string
    {
        return __('Still want them? Book again — they may well still be free.');
    }
}

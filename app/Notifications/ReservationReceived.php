<?php

namespace App\Notifications;

/**
 * The receipt a guest gets the moment they submit a booking.
 *
 * The reference used to be shown once on screen and lost on refresh, so this is the only
 * lasting record the guest has of what they booked.
 */
class ReservationReceived extends ReservationNotification
{
    protected function subject(): string
    {
        return __('We have your booking — :reference', ['reference' => $this->reservation->reference]);
    }

    protected function heading(): string
    {
        return __('Thanks, :name — we have your booking', ['name' => $this->reservation->guestName]);
    }

    protected function intro(): string
    {
        return __('We are verifying your payment now. You will get another email from us once it clears, usually within 24 hours. Keep your reference handy in the meantime.');
    }

    protected function outro(): string
    {
        return __('Nothing is confirmed until you hear from us again, so please hold on to this email.');
    }
}

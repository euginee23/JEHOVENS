<?php

namespace App\Notifications;

/**
 * The receipt a guest gets once their payment has gone through.
 *
 * Sent after PayMongo confirms the money, not when the form is submitted: under the old
 * honour-system GCash flow a booking existed before anyone had checked a payment, and
 * this email had to hedge. It no longer does — by the time it is sent the booking is paid
 * for and confirmed.
 */
class ReservationReceived extends ReservationNotification
{
    protected function subject(): string
    {
        return __('Your booking is confirmed — :reference', ['reference' => $this->reservation->reference]);
    }

    protected function template(): string
    {
        return 'mail.reservation-received';
    }
}

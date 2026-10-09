<?php

namespace App\Notifications;

/**
 * Told to a guest whose payment did not go through.
 *
 * The booking keeps its dates until the hold expires, so this is an invitation to try
 * again rather than a cancellation — a declined card is usually followed by another one
 * a minute later.
 */
class ReservationPaymentFailed extends ReservationNotification
{
    protected function subject(): string
    {
        return __('Your payment did not go through — :reference', ['reference' => $this->reservation->reference]);
    }

    protected function template(): string
    {
        return 'mail.reservation-payment-failed';
    }
}

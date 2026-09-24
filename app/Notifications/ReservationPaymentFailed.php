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

    protected function heading(): string
    {
        return __('We could not take your payment');
    }

    protected function intro(): string
    {
        return __('Your payment of ₱:amount for the booking below was not completed, so it is not confirmed yet. We are holding your dates for a short while — start the booking again to try another payment method.', [
            'amount' => number_format($this->reservation->paid),
        ]);
    }

    protected function outro(): string
    {
        return __('If you think this is a mistake, reply to this email with your reference and we will look into it.');
    }
}

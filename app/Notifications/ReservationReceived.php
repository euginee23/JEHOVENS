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

    protected function heading(): string
    {
        return __('Thanks, :name — you are booked in', ['name' => $this->reservation->guestName]);
    }

    protected function intro(): string
    {
        return __('We have received your payment of ₱:paid and your booking is confirmed. Your payment reference is below — keep this email, and quote your booking reference when you arrive.', [
            'paid' => number_format($this->reservation->paid),
        ]);
    }

    protected function outro(): ?string
    {
        return $this->reservation->balance >= 1
            ? (string) __('Please settle the remaining ₱:balance on arrival.', ['balance' => number_format($this->reservation->balance)])
            : null;
    }
}

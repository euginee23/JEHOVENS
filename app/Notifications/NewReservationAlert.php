<?php

namespace App\Notifications;

/**
 * Tells the resort a guest has just booked something, so someone knows to review it.
 *
 * Bookings land as Pending and sit there until an admin looks at them, so without this
 * nothing prompts anyone to open the admin panel.
 */
class NewReservationAlert extends ReservationNotification
{
    protected function subject(): string
    {
        return __('New :type booking — :reference', [
            'type' => mb_strtolower($this->reservation->type),
            'reference' => $this->reservation->reference,
        ]);
    }

    protected function heading(): string
    {
        return __('New :type booking to review', ['type' => mb_strtolower($this->reservation->type)]);
    }

    protected function intro(): string
    {
        return __(':name has booked :detail and says the payment is sent. It is waiting in the admin panel as pending.', [
            'name' => $this->reservation->guestName,
            'detail' => $this->reservation->detail,
        ]);
    }

    protected function outro(): string
    {
        return __('Contact: :email', ['email' => $this->reservation->guestEmail]);
    }
}

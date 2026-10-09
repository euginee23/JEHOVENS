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

    protected function template(): string
    {
        return 'mail.new-reservation-alert';
    }
}

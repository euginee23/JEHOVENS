<?php

namespace App\Notifications;

use App\Models\ResortSetting;

/**
 * Tells the resort a guest has just booked something, so someone knows to review it.
 *
 * Bookings land as Pending and sit there until an admin looks at them, so without this
 * nothing prompts anyone to open the admin panel.
 *
 * Sent to the resort rather than the guest, so a reply goes to the guest who booked.
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

    protected function replyTo(ResortSetting $resort): ?string
    {
        return $this->reservation->guestEmail;
    }
}

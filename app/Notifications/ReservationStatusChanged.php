<?php

namespace App\Notifications;

use App\Enums\BookingStatus;

/**
 * Sent whenever an admin moves a reservation to a new status.
 *
 * The summary carries the status it has just been moved to, so the subject and the
 * template follow from that rather than from anything the caller has to pass in. Each
 * status has its own template: a booking being marked done reads as a thank-you and a
 * close, not as the same email as a cancellation with different words in it.
 */
class ReservationStatusChanged extends ReservationNotification
{
    protected function subject(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => __('Confirmed — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Completed => __('Your booking is complete — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Cancelled => __('Cancelled — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Pending => __('Reinstated — :reference', ['reference' => $this->reservation->reference]),
        };
    }

    protected function template(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => 'mail.reservation-confirmed',
            BookingStatus::Completed => 'mail.reservation-completed',
            BookingStatus::Cancelled => 'mail.reservation-cancelled',
            BookingStatus::Pending => 'mail.reservation-reinstated',
        };
    }
}

<?php

namespace App\Notifications;

use App\Enums\BookingStatus;

/**
 * Sent whenever an admin moves a reservation to a new status.
 *
 * The summary carries the status it has just been moved to, so the wording follows from
 * that rather than from anything the caller has to pass in.
 */
class ReservationStatusChanged extends ReservationNotification
{
    protected function subject(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => __('Confirmed — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Completed => __('Thank you for staying with us — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Cancelled => __('Cancelled — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Pending => __('Reinstated — :reference', ['reference' => $this->reservation->reference]),
        };
    }

    protected function heading(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => __('Your booking is confirmed'),
            BookingStatus::Completed => __('Thank you, :name', ['name' => $this->reservation->guestName]),
            BookingStatus::Cancelled => __('Your booking has been cancelled'),
            BookingStatus::Pending => __('Your booking is back with us'),
        };
    }

    protected function intro(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => __('Your payment has cleared and the date is held for you. We look forward to having you.'),
            BookingStatus::Completed => __('Everything is settled and your booking is closed. We hope to see you again soon.'),
            BookingStatus::Cancelled => __('This booking has been cancelled and the date has been released. If you did not expect this, please get in touch and we will sort it out.'),
            BookingStatus::Pending => __('This booking has been reinstated and is with us for review again. We will confirm shortly.'),
        };
    }

    protected function outro(): ?string
    {
        if ($this->reservation->status !== BookingStatus::Confirmed || $this->reservation->balance < 1) {
            return null;
        }

        return __('Please settle the remaining ₱:balance on arrival.', [
            'balance' => number_format($this->reservation->balance),
        ]);
    }
}

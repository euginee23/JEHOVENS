<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use LogicException;

/**
 * Sent whenever an admin moves a reservation to a new status.
 *
 * The summary carries the status it has just been moved to, so the subject and the
 * template follow from that rather than from anything the caller has to pass in. Each
 * status has its own template: a booking being marked done reads as a thank-you and a
 * close, not as the same email as a cancellation with different words in it.
 *
 * There is none for Pending: nothing moves a booking back there any more (see
 * BookingStatus::transitions()), so that arm is only here because the match has to
 * cover every case.
 */
class ReservationStatusChanged extends ReservationNotification
{
    protected function subject(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => __('Confirmed — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Completed => __('Your booking is complete — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Cancelled => __('Cancelled — :reference', ['reference' => $this->reservation->reference]),
            BookingStatus::Pending => throw self::noEmailForPending(),
        };
    }

    protected function template(): string
    {
        return match ($this->reservation->status) {
            BookingStatus::Confirmed => 'mail.reservation-confirmed',
            BookingStatus::Completed => 'mail.reservation-completed',
            BookingStatus::Cancelled => 'mail.reservation-cancelled',
            BookingStatus::Pending => throw self::noEmailForPending(),
        };
    }

    /**
     * The error for an email about a move that can no longer happen.
     */
    private static function noEmailForPending(): LogicException
    {
        return new LogicException('A booking cannot be moved back to pending, so there is no email for it.');
    }
}

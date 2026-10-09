<?php

namespace App\Notifications;

use App\Support\ReservationSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every email the resort sends about a reservation.
 *
 * Each email has its own Blade template under resources/views/mail, so its wording can be
 * edited on its own. They all show the same block of booking details, built from a
 * ReservationSummary and drawn by the shared mail.partials.reservation-details partial.
 *
 * Guests mostly book without an account, so these are sent to an address rather than to
 * a User — see ManagesReservationLifecycle::notifyGuest().
 */
abstract class ReservationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReservationSummary $reservation) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The subject line.
     */
    abstract protected function subject(): string;

    /**
     * The Blade view this email is written in.
     */
    abstract protected function template(): string;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->markdown($this->template(), ['reservation' => $this->reservation]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->reservation->reference,
            'type' => $this->reservation->type,
            'status' => $this->reservation->status->value,
        ];
    }
}

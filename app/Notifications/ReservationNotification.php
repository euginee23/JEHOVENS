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
 * All of them show the same block of booking details, so they are all built from a
 * ReservationSummary and share one Blade template. A subclass only supplies the wording
 * around it.
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
     * The heading at the top of the email.
     */
    abstract protected function heading(): string;

    /**
     * The sentence explaining why this email arrived.
     */
    abstract protected function intro(): string;

    /**
     * An optional closing line, below the booking details.
     */
    protected function outro(): ?string
    {
        return null;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.reservation', [
                'reservation' => $this->reservation,
                'heading' => $this->heading(),
                'intro' => $this->intro(),
                'outro' => $this->outro(),
            ]);
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

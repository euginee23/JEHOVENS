<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message whose only job is to prove the mail settings work.
 *
 * Always sent straight out rather than queued, from the Mail settings page, so the admin
 * sees the mail server's real answer.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $sentBy) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Mail check — :app', ['app' => config('app.name')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.test',
            with: [
                'mailer' => (string) config('mail.default'),
                'sentAt' => now()->format('F j, Y g:i A'),
            ],
        );
    }
}

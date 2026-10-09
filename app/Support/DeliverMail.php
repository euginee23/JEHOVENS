<?php

namespace App\Support;

use App\Notifications\ReservationNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Send a booking email to an address, in whichever delivery mode the deployment chose.
 *
 * Mail goes out immediately by default, so it leaves even on a server with no queue
 * worker — a queued email with no worker to send it waits in the jobs table forever
 * without a word. A deployment that does run a worker can opt into queueing
 * (RESORT_MAIL_QUEUED=true) so a slow mail server never delays the request that
 * triggered the email.
 *
 * A failed immediate send is reported rather than thrown: the email accompanies something
 * that has already happened — a payment, a status change — and must not undo or break it.
 * It is also remembered for the rest of the request, so the page that triggered it can
 * tell staff the guest was not told (see failures()). Bound as scoped, so that memory
 * never outlives the request or queued job it belongs to.
 */
class DeliverMail
{
    /**
     * Addresses an immediate send failed for during this request.
     *
     * @var array<int, string>
     */
    private array $failures = [];

    /**
     * @return bool whether the email was handed off without error
     */
    public function __invoke(string $address, ReservationNotification $notification): bool
    {
        $recipient = Notification::route('mail', $address);

        if (config('resort.mail.queued')) {
            // After the commit, so a worker never picks up an email about a booking whose
            // transaction then rolls back — or has not committed yet.
            $recipient->notify(
                $notification->onQueue((string) config('resort.mail.queue'))->afterCommit(),
            );

            return true;
        }

        try {
            $recipient->notifyNow($notification);
        } catch (Throwable $exception) {
            report($exception);

            $this->failures[] = $address;

            return false;
        }

        return true;
    }

    /**
     * Addresses an email could not be sent to during this request.
     *
     * @return array<int, string>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}

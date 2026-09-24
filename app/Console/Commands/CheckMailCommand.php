<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Work out why a booking email never arrived.
 *
 * There are two quite different failures behind "no email came", and they need opposite
 * fixes: the mail transport is wrong, or nothing is draining the queue. Booking mail is
 * queued, so a perfectly good SMTP setup still sends nothing at all without a worker —
 * which is the usual answer, and impossible to tell apart from a broken mailer by looking
 * at an empty inbox.
 *
 * This sends one mail straight out, skipping the queue, so the transport is proved or
 * disproved on its own.
 */
class CheckMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resort:mail-check
                            {--to= : Where to send the test message. Defaults to RESORT_MAIL_TEST_ADDRESS, then the resort notification address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the mail transport and the queue that booking email depends on';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->reportMailer();
        $this->reportQueue();

        return $this->sendTestMessage();
    }

    /**
     * What the application will try to send through.
     */
    private function reportMailer(): void
    {
        $mailer = (string) config('mail.default');

        $this->components->twoColumnDetail('<fg=gray>Mailer</>', $mailer);

        if ($mailer === 'log') {
            $this->components->twoColumnDetail('<fg=gray>Delivery</>', '<fg=yellow>writes to the log, sends nothing</>');
        }

        if ($mailer === 'smtp') {
            $this->components->twoColumnDetail('<fg=gray>Host</>', (string) config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->components->twoColumnDetail('<fg=gray>Username</>', (string) config('mail.mailers.smtp.username') ?: '<fg=yellow>not set</>');
        }

        $this->components->twoColumnDetail('<fg=gray>From</>', (string) config('mail.from.address'));
        $this->components->twoColumnDetail('<fg=gray>Resort alerts to</>', (string) config('resort.notifications.admin_email'));
        $this->newLine();
    }

    /**
     * Whether anything is draining the queue booking mail sits in.
     */
    private function reportQueue(): void
    {
        $connection = (string) config('queue.default');

        $this->components->twoColumnDetail('<fg=gray>Queue</>', $connection);

        if ($connection === 'sync') {
            $this->components->twoColumnDetail('<fg=gray>Worker</>', 'not needed — mail is sent inline');
            $this->newLine();

            return;
        }

        try {
            $waiting = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $this->components->twoColumnDetail('<fg=gray>Jobs</>', '<fg=yellow>could not be read</>');
            $this->newLine();

            return;
        }

        // Jobs piling up is the signature of a worker that is not running: mail is being
        // queued correctly and then nobody is picking it up.
        $this->components->twoColumnDetail(
            '<fg=gray>Jobs waiting</>',
            $waiting > 0 ? "<fg=yellow>{$waiting} — is `php artisan queue:work` running?</>" : '0',
        );

        $this->components->twoColumnDetail(
            '<fg=gray>Jobs failed</>',
            $failed > 0 ? "<fg=red>{$failed} — see `php artisan queue:failed`</>" : '0',
        );

        $this->newLine();
    }

    /**
     * Send one message immediately, so the transport is tested on its own.
     */
    private function sendTestMessage(): int
    {
        $to = (string) (
            $this->option('to')
            ?: config('resort.notifications.test_email')
            ?: config('resort.notifications.admin_email')
        );

        if ($to === '') {
            $this->components->error('Nowhere to send to. Set RESORT_MAIL_TEST_ADDRESS, or pass --to=you@example.com.');

            return self::FAILURE;
        }

        $this->components->task("Sending a test message to {$to}", function () use ($to) {
            Mail::raw(
                'This is a test from '.config('app.name').', sent at '.now()->toDateTimeString().".\n\n"
                ."It was sent straight out rather than queued, so receiving it proves the mail\n"
                ."transport works. If real booking email still never arrives, the queue worker\n"
                .'is what is missing, not the mailer.',
                fn ($message) => $message->to($to)->subject('Mail check — '.config('app.name')),
            );

            return true;
        });

        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->components->warn('MAIL_MAILER is `log`, so that went to storage/logs, not to an inbox.');

            return self::SUCCESS;
        }

        $this->components->info('Sent with no error. If it does not arrive, the mail provider accepted and then dropped it — check that MAIL_FROM_ADDRESS is an address the provider lets you send as, and look in spam.');

        return self::SUCCESS;
    }
}

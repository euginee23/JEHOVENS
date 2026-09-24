<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| A booking holds its dates from the moment the guest is sent to PayMongo, so
| an abandoned checkout keeps them until this releases them. It needs
| `schedule:run` on a one-minute cron in production — without it those dates
| are never sold again.
|
*/

Schedule::command('resort:expire-unpaid-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Draining the queue without a worker
|--------------------------------------------------------------------------
|
| Only when QUEUE_DRAIN_ON_SCHEDULE is on, for hosting that will not run a
| long-lived `queue:work`. `--stop-when-empty` makes each run finish rather
| than sit there, and `--max-time` stops one run overlapping the next even
| if the queue is busy.
|
| Leave it off where a real worker runs: two things draining one queue is
| not harmful, but it makes a stuck job much harder to reason about.
|
*/

if (config('queue.drain_on_schedule')) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
        ->everyMinute()
        ->withoutOverlapping();
}

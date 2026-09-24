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

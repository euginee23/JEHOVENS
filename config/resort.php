<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Administrator
    |--------------------------------------------------------------------------
    |
    | Used by AdminSeeder to create the first account for /admin. The defaults
    | are development credentials — set ADMIN_EMAIL and ADMIN_PASSWORD in .env
    | before seeding anywhere reachable from the internet, or skip the seeder
    | entirely and use `php artisan resort:make-admin`.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@admin.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Where the resort is told about new bookings. This falls back to the seeded
    | administrator so a fresh install still delivers somewhere, but set
    | RESORT_NOTIFICATION_EMAIL to the address staff actually watch.
    |
    | Guest mail goes to the address on the booking itself — most guests book
    | without an account, so there is no user record to notify.
    |
    | Note that MAIL_MAILER defaults to `log`, which writes mail to the log
    | rather than sending it. Point it at a real transport before going live.
    |
    */

    'notifications' => [
        // `?:` rather than an env() default, so an empty RESORT_NOTIFICATION_EMAIL=
        // line in .env falls back too instead of leaving nowhere to deliver.
        'admin_email' => env('RESORT_NOTIFICATION_EMAIL') ?: env('ADMIN_EMAIL', 'admin@admin.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Delivery
    |--------------------------------------------------------------------------
    |
    | By default every booking email is sent immediately, during the request
    | that triggers it, so it leaves even on a server with no queue worker.
    | Set RESORT_MAIL_QUEUED=true only where a worker (`php artisan queue:work`)
    | or QUEUE_DRAIN_ON_SCHEDULE is kept running — without one, queued emails
    | wait in the jobs table and are never sent.
    |
    */

    'mail' => [
        'queued' => (bool) env('RESORT_MAIL_QUEUED', false),
        'queue' => env('RESORT_MAIL_QUEUE', 'default'),
    ],

];

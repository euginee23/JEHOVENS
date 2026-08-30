<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GCash Payment Details
    |--------------------------------------------------------------------------
    |
    | Shown to guests on the booking pages so they know where to send their 50%
    | downpayment. These MUST be set to the resort's real account before the site
    | goes live — the defaults below are the placeholders from the design mockup
    | and will send guests' money nowhere.
    |
    */

    'gcash' => [
        'number' => env('RESORT_GCASH_NUMBER', '09123456789'),
        'account_name' => env('RESORT_GCASH_ACCOUNT_NAME', 'Jehoven Resort Enterprises'),
    ],

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
    | rather than sending it, and that queued mail needs a running worker
    | (`php artisan queue:work`). Both must be set up before going live.
    |
    */

    'notifications' => [
        // `?:` rather than an env() default, so an empty RESORT_NOTIFICATION_EMAIL=
        // line in .env falls back too instead of leaving nowhere to deliver.
        'admin_email' => env('RESORT_NOTIFICATION_EMAIL') ?: env('ADMIN_EMAIL', 'admin@admin.com'),
    ],

];

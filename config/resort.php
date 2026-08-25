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

];

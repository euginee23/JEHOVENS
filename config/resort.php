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

];

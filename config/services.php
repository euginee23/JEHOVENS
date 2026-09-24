<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PayMongo
    |--------------------------------------------------------------------------
    |
    | Every booking is paid for through PayMongo Checkout, which hands back a
    | payment reference the resort can verify a booking against. Without a secret
    | key the booking pages say so and refuse to take a booking, rather than
    | recording one nobody paid for.
    |
    | The webhook secret is shown once, when the endpoint is created — either on
    | the dashboard's webhook screen or in the response to `POST /v1/webhooks`.
    | Save it then; it cannot be read back afterwards.
    |
    */

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),

        // What the guest may pay with, as a comma-separated list.
        //
        // QR Ph is the resort's only active method: the guest scans with any bank or
        // e-wallet app and it settles over InstaPay, which needs no merchant wallet of
        // its own. The e-wallet methods sit inactive on the account until it is verified.
        //
        // Naming a method the account does NOT have narrows the checkout page to nothing,
        // and the guest is told "No payment methods are available" only after committing
        // to a booking. Set PAYMONGO_METHODS empty to name none of them, which lets
        // PayMongo offer whatever is enabled — worth doing the day the wallets go live.
        'methods' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('PAYMONGO_METHODS', 'qrph'))),
        )),

        // How long a booking holds its dates while the guest is on PayMongo's page.
        // The sweeper releases anything still unpaid after this, so `schedule:run`
        // has to be running or abandoned checkouts keep their dates forever.
        'hold_minutes' => (int) env('PAYMONGO_HOLD_MINUTES', 60),
    ],

];

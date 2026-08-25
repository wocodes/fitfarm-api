<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FitCloud SaaS Configuration
    |--------------------------------------------------------------------------
    */

    'platform_fee_percentage' => env('FITCLOUD_PLATFORM_FEE_PERCENTAGE', 1.0), // 1%

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL', 'billing@fitcloud.io'),
    ],

    'trial_days' => 14,
    'default_max_members' => 100,
];

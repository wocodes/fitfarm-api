<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FitCloud SaaS Configuration
    |--------------------------------------------------------------------------
    */

    'platform_fee_percentage' => env('FITCLOUD_PLATFORM_FEE_PERCENTAGE', 1.0), // 1%

    // Controls the public app URLs returned by Tenant::memberPortalUrl() / adminConsoleUrl().
    'app_urls' => [
        'admin' => env('ADMIN_APP_URL', ''),
        'member' => env('MEMBER_APP_URL', ''),
    ],

    // When empty, per-gym subdomains are derived from PLATFORM_DOMAIN,
    // e.g. {slug}.fitfarmhq.com and admin.fitfarmhq.com.
    'platform_domain' => env('PLATFORM_DOMAIN', ''),

    // When true, member purchases are verified against Paystack before activation.
    'verify_paystack_references' => env('PAYSTACK_VERIFY_REFERENCES', false),

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL', 'billing@fitfarmhq.com'),
    ],

    'trial_days' => 14,
    'default_max_members' => 100,

    /*
    |--------------------------------------------------------------------------
    | One-time platform administrator bootstrap
    |--------------------------------------------------------------------------
    |
    | These values are consumed by DatabaseSeeder. They live in config so they
    | still work when the production configuration cache is enabled.
    |
    */
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'name' => env('SUPER_ADMIN_NAME', 'Platform Admin'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];

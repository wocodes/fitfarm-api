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

    /*
    |--------------------------------------------------------------------------
    | Payment provider
    |--------------------------------------------------------------------------
    |
    | Bachs is the active checkout provider. Paystack settings remain only for
    | legacy records and the old webhook endpoint.
    |
    */
    'bachs' => [
        'secret_key' => env('BACHS_SECRET_KEY', ''),
        'base_url' => env('BACHS_BASE_URL', 'https://api.bachs.io'),
        'webhook_secret' => env('BACHS_WEBHOOK_SECRET', ''),
        'currency' => env('BACHS_CURRENCY', 'NGN'),
        // Demo tenants always use sandbox credentials and can never reach a
        // live checkout, even when the platform is running live payments.
        'demo_secret_key' => env('BACHS_DEMO_SECRET_KEY', ''),
        'demo_base_url' => env('BACHS_DEMO_BASE_URL', 'https://sandbox-api.bachs.io'),
        'demo_webhook_secret' => env('BACHS_DEMO_WEBHOOK_SECRET', ''),
    ],

    'demo' => [
        'days' => (int) env('DEMO_DURATION_DAYS', 30),
        'grace_days' => (int) env('DEMO_GRACE_DAYS', 7),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL', 'billing@fitfarmhq.com'),
    ],

    'trial_days' => 90,
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

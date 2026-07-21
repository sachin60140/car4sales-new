<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reference number sequences
    |--------------------------------------------------------------------------
    | Each workflow document type has a configurable prefix. Set per_branch to
    | true to maintain independent sequences per branch.
    */

    'sequences' => [
        'purchase_lead' => ['prefix' => 'PL', 'padding' => 6, 'per_branch' => false],
        'inspection' => ['prefix' => 'INS', 'padding' => 6, 'per_branch' => false],
        'purchase' => ['prefix' => 'PUR', 'padding' => 6, 'per_branch' => false],
        'stock' => ['prefix' => 'STK', 'padding' => 6, 'per_branch' => false],
        'sales_lead' => ['prefix' => 'SL', 'padding' => 6, 'per_branch' => false],
        'visit' => ['prefix' => 'VIS', 'padding' => 6, 'per_branch' => false],
        'test_drive' => ['prefix' => 'TD', 'padding' => 6, 'per_branch' => false],
        'booking' => ['prefix' => 'BKG', 'padding' => 6, 'per_branch' => false],
        'payment' => ['prefix' => 'PAY', 'padding' => 6, 'per_branch' => false],
        'delivery' => ['prefix' => 'DLV', 'padding' => 6, 'per_branch' => false],
        'rto_case' => ['prefix' => 'RTO', 'padding' => 6, 'per_branch' => false],
        'approval' => ['prefix' => 'APR', 'padding' => 6, 'per_branch' => false],
        'employee' => ['prefix' => 'EMP', 'padding' => 4, 'per_branch' => false],
        'workshop_job' => ['prefix' => 'WJ', 'padding' => 6, 'per_branch' => false],
        'vehicle_expense' => ['prefix' => 'EXP', 'padding' => 6, 'per_branch' => false],
        'enquiry' => ['prefix' => 'ENQ', 'padding' => 6, 'per_branch' => false],
        'customer' => ['prefix' => 'CUST', 'padding' => 6, 'per_branch' => false],
        'booking_payment' => ['prefix' => 'BPAY', 'padding' => 6, 'per_branch' => false],
        'refund' => ['prefix' => 'REF', 'padding' => 6, 'per_branch' => false],
        'finance_application' => ['prefix' => 'FIN', 'padding' => 6, 'per_branch' => false],
        'disbursement' => ['prefix' => 'DISB', 'padding' => 6, 'per_branch' => false],
        'invoice' => ['prefix' => 'INV', 'padding' => 6, 'per_branch' => false],
        'receipt' => ['prefix' => 'RCPT', 'padding' => 6, 'per_branch' => false],
        'vendor_submission' => ['prefix' => 'VSUB', 'padding' => 6, 'per_branch' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public website
    |--------------------------------------------------------------------------
    */

    'public' => [
        'company_name' => 'Car4Sales',
        'tagline' => 'Deals Pre-owned Cars',
        'phone' => env('PUBLIC_PHONE', '+91 90000 00000'),
        'whatsapp' => env('PUBLIC_WHATSAPP', '919000000000'),
        'email' => env('PUBLIC_EMAIL', 'hello@car4sales.in'),
        // Used on printed/legal documents (booking slip, agreements).
        'address' => env('PUBLIC_ADDRESS', 'Car4Sales Motors, Main Road, Lucknow, Uttar Pradesh 226001'),
        'gstin' => env('PUBLIC_GSTIN', ''),
        'jurisdiction' => env('PUBLIC_JURISDICTION', 'Lucknow'),
        // OTP verification for public forms. Off by default in local/testing;
        // enable in production via PUBLIC_REQUIRE_OTP=true.
        'require_otp' => env('PUBLIC_REQUIRE_OTP', false),
        'otp_ttl_minutes' => 10,
        // Duplicate enquiry window (same mobile + type + vehicle) in hours.
        'duplicate_window_hours' => 12,
        // Default finance assumptions for the on-site EMI estimator.
        'finance' => [
            'interest_rate' => 11.5,
            'tenure_months' => 60,
            'down_payment_pct' => 15,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password policy
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => 8,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => false,
        'expiry_days' => null, // set an integer to force periodic rotation
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The in-app "database" channel is always on. Outbound channels (mail, sms,
    | whatsapp, push) are toggled here and each resolves a driver. The "log"
    | driver records a delivery row and writes to the log without contacting a
    | live provider — the default so the platform runs on XAMPP without SMS /
    | WhatsApp / FCM credentials. Swap the driver in production.
    |
    */

    'notifications' => [
        'channels' => [
            'mail' => (bool) env('C4S_NOTIFY_MAIL', true),
            'sms' => (bool) env('C4S_NOTIFY_SMS', false),
            'whatsapp' => (bool) env('C4S_NOTIFY_WHATSAPP', false),
            'push' => (bool) env('C4S_NOTIFY_PUSH', true),
        ],
        'drivers' => [
            'mail' => env('C4S_MAIL_DRIVER', env('MAIL_MAILER', 'log')),
            'sms' => env('C4S_SMS_DRIVER', 'log'),
            'whatsapp' => env('C4S_WHATSAPP_DRIVER', 'log'),
            'push' => env('C4S_PUSH_DRIVER', 'log'),
        ],
    ],
];

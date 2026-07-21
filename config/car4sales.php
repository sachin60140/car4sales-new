<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking slip — default terms & conditions
    |--------------------------------------------------------------------------
    | Default used-vehicle booking terms (Indian law). Editable at runtime from
    | Admin → Settings, which stores an override in the `settings` table under
    | the `booking_terms` key (one clause per line).
    */

    'booking_terms' => [
        'This booking slip is a receipt for the booking/token amount and an agreement to purchase the above pre-owned vehicle. The booking amount is an advance and is adjusted against the total sale consideration.',
        'The vehicle is a used / pre-owned vehicle and is sold strictly on an "as-is-where-is" and "no-warranty" basis. The Buyer confirms having inspected (and/or test-driven) the vehicle and is fully satisfied with its condition, fitness, mileage and documents. No implied warranty of merchantability or fitness under the Sale of Goods Act, 1930 shall apply, save for any separate written warranty issued by the Company.',
        'The balance amount is payable in full before delivery. Delivery shall be made only after realisation of full payment and completion of documentation/finance formalities. Any cheque/UPI/NEFT is subject to realisation.',
        'Ownership and possession pass to the Buyer only on delivery against full payment. Until then the vehicle remains the property of the Company.',
        'Transfer of registration/ownership shall be carried out as per the Motor Vehicles Act, 1988 and the Central Motor Vehicles Rules, 1989. RTO transfer charges, road tax, smart-card, hypothecation/termination and other statutory charges are payable by the Buyer unless expressly agreed in writing.',
        'The Buyer shall be responsible for insurance transfer/renewal from the date of delivery. The Company is not liable for any claim arising after delivery.',
        'From the date/time of delivery, the Buyer is solely responsible for the vehicle\'s use, road-safety compliance, traffic challans, penalties and any third-party liability. The Company shall not be liable for any accident, loss, or violation thereafter.',
        'Prices are inclusive of applicable taxes as per prevailing GST law; any statutory levy revised by the government shall be borne by the Buyer.',
        'Cancellation & refund: if the Buyer cancels the booking, the booking/token amount may be refunded or forfeited as per the Company\'s cancellation policy and the reason for cancellation. Refunds, where approved, are processed to the original payment source within a reasonable period.',
        'Delivery timelines are indicative and subject to document verification, finance/loan approval and RTO processes; delays on these accounts shall not be a ground for cancellation or compensation.',
        'This slip supersedes oral representations. Any addition/alteration is valid only if recorded in writing and signed by an authorised signatory of the Company.',
        'This agreement is governed by the laws of India. All disputes are subject to the exclusive jurisdiction of the courts at the Company\'s registered location.',
    ],

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

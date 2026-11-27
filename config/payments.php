<?php

use App\Services\Payments\AfsGateway;
use App\Services\Payments\DisabledGateway;

return [
    'driver' => env('PAYMENT_GATEWAY', 'disabled'),
    'environment' => env('PAYMENT_ENVIRONMENT', 'test'),
    'currency' => env('PAYMENT_CURRENCY', 'BHD'),
    'default_type' => env('PAYMENT_DEFAULT_TYPE', 'DB'),
    'maximum_amount' => '100000.000',
    'display_timezone' => 'Asia/Kolkata',
    // Minutes an existing provider checkout session may be reused instead of replaced.
    'checkout_reuse_minutes' => 25,
    // Standalone fees selectable on payment forms in addition to application services.
    'fee_services' => [
        'registration_fee' => 'Registration Fee',
        'after_visa_fee' => 'After Visa Fee',
        'work_permit_fee' => 'Work Permit Fee',
    ],
    // Register an approved, tested provider adapter here before enabling checkout.
    'gateways' => [
        'disabled' => DisabledGateway::class,
        'afs' => AfsGateway::class,
    ],
    // Exact HTTPS hosts that a gateway may redirect the shopper to.
    'checkout_hosts' => ['eu-test.oppwa.com', 'eu-prod.oppwa.com'],
    'api_key' => env('PAYMENT_API_KEY'),
    'secret' => env('PAYMENT_SECRET'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    'afs' => [
        // Test: https://eu-test.oppwa.com/ | Live: https://eu-prod.oppwa.com/
        'base_url' => env('PAYMENT_BASE_URL', env('AFS_BASE_URL', 'https://eu-test.oppwa.com/')),
        'entity_id' => env('PAYMENT_ENTITY_ID', env('AFS_ENTITY_ID')),
        'access_token' => env('PAYMENT_ACCESS_TOKEN', env('AFS_ACCESS_TOKEN')),
        // 64-character hexadecimal secret configured under Administration > Webhooks.
        'webhook_secret' => env('AFS_WEBHOOK_SECRET'),
        'brands' => env('AFS_BRANDS', 'VISA MASTER'),
        'widget_integrity' => filter_var(env('AFS_WIDGET_INTEGRITY', true), FILTER_VALIDATE_BOOL),
        'descriptor' => env('AFS_DESCRIPTOR', 'Edu World'),
        // Optional in the test environment only: INTERNAL (simulator) or EXTERNAL (acquirer test).
        'test_mode' => env('AFS_TEST_MODE'),
    ],
];

<?php

use App\Services\Payments\AfsGateway;
use App\Services\Payments\DisabledGateway;

return [
    'driver' => env('PAYMENT_GATEWAY', 'disabled'),
    'currency' => 'BHD',
    'maximum_amount' => '100000.000',
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
        'base_url' => env('AFS_BASE_URL', 'https://eu-test.oppwa.com/'),
        'entity_id' => env('AFS_ENTITY_ID'),
        'access_token' => env('AFS_ACCESS_TOKEN'),
        // 64-character hexadecimal secret configured under Administration > Webhooks.
        'webhook_secret' => env('AFS_WEBHOOK_SECRET'),
        'brands' => env('AFS_BRANDS', 'VISA MASTER'),
        'descriptor' => env('AFS_DESCRIPTOR', 'Edu World'),
        // Optional in the test environment only: INTERNAL (simulator) or EXTERNAL (acquirer test).
        'test_mode' => env('AFS_TEST_MODE'),
    ],
];

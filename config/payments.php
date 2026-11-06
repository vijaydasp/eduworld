<?php

use App\Services\Payments\DisabledGateway;

return [
    'driver' => env('PAYMENT_GATEWAY', 'disabled'),
    'currency' => 'BHD',
    'maximum_amount' => '100000.000',
    // Standalone fees selectable on payment forms in addition to application services.
    'fee_services' => [
        'registration_fee' => 'Registration Fee',
        'after_visa_fee' => 'After Visa Fee',
        'work_permit_fee' => 'Work Permit Fee',
    ],
    // Register an approved, tested provider adapter here before enabling checkout.
    'gateways' => ['disabled' => DisabledGateway::class],
    'checkout_hosts' => [],
    'api_key' => env('PAYMENT_API_KEY'),
    'secret' => env('PAYMENT_SECRET'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
];

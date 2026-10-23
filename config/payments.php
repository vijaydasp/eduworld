<?php

use App\Services\Payments\DisabledGateway;

return [
    'driver' => env('PAYMENT_GATEWAY', 'disabled'),
    'currency' => 'BHD',
    'maximum_amount' => '100000.000',
    // Register an approved, tested provider adapter here before enabling checkout.
    'gateways' => ['disabled' => DisabledGateway::class],
    'checkout_hosts' => [],
    'api_key' => env('PAYMENT_API_KEY'),
    'secret' => env('PAYMENT_SECRET'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
];

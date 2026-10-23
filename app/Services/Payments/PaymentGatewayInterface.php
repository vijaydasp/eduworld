<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function available(): bool;

    /** Use the transaction reference as the provider's idempotency key. */
    public function initiate(Payment $payment, string $returnUrl): GatewayCheckout;

    /**
     * Authenticate the raw body/signature, reject expired signatures, and verify
     * the transaction with the provider as required before constructing this DTO.
     * Never construct a verified event from unverified browser/request fields.
     * Throw InvalidPaymentWebhook for untrusted or unverifiable callbacks.
     */
    public function verifyWebhook(Request $request): VerifiedPaymentEvent;
}

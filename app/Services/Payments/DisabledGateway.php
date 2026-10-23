<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use LogicException;

final class DisabledGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'disabled';
    }

    public function available(): bool
    {
        return false;
    }

    public function initiate(Payment $payment, string $returnUrl): GatewayCheckout
    {
        throw new LogicException('An approved payment gateway has not been configured.');
    }

    public function verifyWebhook(Request $request): VerifiedPaymentEvent
    {
        throw new InvalidPaymentWebhook('There is no configured payment gateway.');
    }
}

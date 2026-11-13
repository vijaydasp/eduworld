<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * A gateway that can reconcile the shopper's browser return by querying the provider
 * server-to-server. Browser parameters are treated only as lookup hints, never as proof.
 */
interface VerifiesCheckoutReturn
{
    public function verifyCheckoutReturn(Payment $payment, Request $request): ?VerifiedPaymentEvent;
}

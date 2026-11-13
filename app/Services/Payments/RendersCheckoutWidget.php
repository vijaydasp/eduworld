<?php

namespace App\Services\Payments;

use App\Models\Payment;

/** A gateway whose checkout form must be rendered on our own page instead of redirecting away. */
interface RendersCheckoutWidget
{
    /** The HTTPS script URL that loads the provider's payment form for a checkout reference. */
    public function widgetScriptUrl(string $checkoutReference): string;

    /** Rebuild the checkout DTO for an already-created provider session without initiating a new one. */
    public function resumeCheckout(Payment $payment): GatewayCheckout;
}

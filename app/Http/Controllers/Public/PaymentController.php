<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\DeferredPaymentWebhook;
use App\Services\Payments\InvalidPaymentWebhook;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\RendersCheckoutWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Public arbitrary payment creation is disabled. Signed payment-request links
     * issued by staff are the only way to start a new payment.
     */
    public function create(): RedirectResponse
    {
        return to_route('contact');
    }

    public function store(): RedirectResponse
    {
        return to_route('contact');
    }

    public function checkout(Payment $payment, PaymentManager $payments): Response
    {
        $gateway = $payments->gateway();

        abort_unless($gateway instanceof RendersCheckoutWidget, 503, 'Online payments are not configured.');
        abort_unless($payment->channel === 'gateway' && $payment->status === 'pending', 404);
        abort_unless($payment->gateway_reference && hash_equals($gateway->name(), (string) $payment->gateway), 404);

        $scriptUrl = $gateway->widgetScriptUrl($payment->gateway_reference);
        $origin = parse_url($scriptUrl, PHP_URL_SCHEME).'://'.parse_url($scriptUrl, PHP_URL_HOST);

        $contentSecurityPolicy = implode('; ', [
            "default-src 'self'",
            // The AFS COPYandPAY widget injects its own inline bootstrap script, so a
            // nonce/hash cannot be used here. Everything else stays locked down.
            "script-src 'self' 'unsafe-inline' {$origin}",
            "style-src 'self' 'unsafe-inline' {$origin}",
            "font-src 'self' {$origin}",
            "img-src 'self' data: {$origin}",
            "connect-src 'self' {$origin}",
            "frame-src {$origin}",
            "form-action 'self' {$origin}",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
        ]);

        return response()->view('payments.checkout', [
            'payment' => $payment,
            'scriptUrl' => $scriptUrl,
            'resultUrl' => $payments->returnUrl($payment),
            'brands' => (string) config('payments.afs.brands', 'VISA MASTER'),
        ])->withHeaders(['Content-Security-Policy' => $contentSecurityPolicy]);
    }

    public function gatewayReturn(Request $request, string $reference, PaymentManager $payments): RedirectResponse
    {
        $payment = Payment::where('transaction_reference', $reference)->firstOrFail();
        $payments->reconcileCheckout($payment, $request);

        return redirect($payments->receiptUrl($payment->fresh()));
    }

    public function receipt(Payment $payment): View
    {
        return view('payments.receipt', compact('payment'));
    }

    public function webhook(Request $request, string $gateway, PaymentManager $payments): JsonResponse
    {
        try {
            $payments->processWebhook($gateway, $request);
        } catch (DeferredPaymentWebhook) {
            return response()->json(['received' => true, 'status' => 'pending'], 202);
        } catch (InvalidPaymentWebhook) {
            abort(403, 'Payment notification could not be verified.');
        }

        return response()->json(['received' => true]);
    }
}

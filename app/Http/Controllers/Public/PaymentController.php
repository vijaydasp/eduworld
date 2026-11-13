<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\Payments\DeferredPaymentWebhook;
use App\Services\Payments\InvalidPaymentWebhook;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\RendersCheckoutWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(PaymentManager $payments): View
    {
        return view('payments.create', [
            'gatewayAvailable' => $payments->gateway()->available(),
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentManager $payments): RedirectResponse
    {
        $gateway = $payments->gateway();

        if (! $gateway->available()) {
            return to_route('pay')->withInput()->withErrors([
                'payment' => 'Online payment is currently unavailable. Please contact our team for assistance.',
            ]);
        }

        $payment = $payments->createIntent($request->validated());
        $checkout = $payments->checkout($payment);

        if ($checkout === null) {
            Log::warning('Public payment checkout could not be started.', [
                'payment_id' => $payment->id,
                'gateway' => $gateway->name(),
            ]);

            return to_route('pay')->withInput()->withErrors([
                'payment' => 'We could not start the secure payment. Please try again or contact our team.',
            ]);
        }

        if ($gateway instanceof RendersCheckoutWidget) {
            return redirect()->to(URL::temporarySignedRoute(
                'pay.checkout',
                now()->addMinutes(35),
                ['payment' => $payment->id],
            ));
        }

        return redirect()->away($checkout->url);
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

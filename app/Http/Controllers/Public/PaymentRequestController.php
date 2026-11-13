<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentRequestManager;
use App\Services\Payments\RendersCheckoutWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PaymentRequestController extends Controller
{
    public function show(PaymentRequest $paymentRequest, PaymentRequestManager $manager, PaymentManager $payments): View
    {
        $manager->expireIfNeeded($paymentRequest);
        $paymentRequest->loadMissing(['application', 'payment']);

        abort_if(in_array($paymentRequest->status, ['cancelled', 'expired'], true), 410, 'This payment request is no longer available.');

        $receiptUrl = $paymentRequest->payment
            ? URL::temporarySignedRoute('pay.receipt', now()->addDay(), ['payment' => $paymentRequest->payment->id])
            : null;

        return view('payments.request', [
            'paymentRequest' => $paymentRequest,
            'receiptUrl' => $receiptUrl,
            'gatewayAvailable' => $payments->gateway()->available(),
        ]);
    }

    /**
     * Signed link used by the payment-request email. It starts (or reuses) the
     * checkout and hands the customer straight to the AFS COPYandPAY form.
     */
    public function checkout(PaymentRequest $paymentRequest, PaymentRequestManager $manager, PaymentManager $payments): RedirectResponse
    {
        return $this->beginCheckout($paymentRequest, $manager, $payments);
    }

    public function pay(PaymentRequest $paymentRequest, PaymentRequestManager $manager, PaymentManager $payments): RedirectResponse
    {
        return $this->beginCheckout($paymentRequest, $manager, $payments);
    }

    private function beginCheckout(PaymentRequest $paymentRequest, PaymentRequestManager $manager, PaymentManager $payments): RedirectResponse
    {
        $manager->expireIfNeeded($paymentRequest);

        abort_if(in_array($paymentRequest->status, ['cancelled', 'expired'], true), 410, 'This payment request is no longer available.');

        $payment = $manager->start($paymentRequest);
        $checkout = $payments->checkout($payment);

        if ($checkout === null) {
            Log::warning('Payment request checkout could not be started.', [
                'payment_request_id' => $paymentRequest->id,
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
            ]);

            return redirect(URL::temporarySignedRoute(
                'pay.request.show',
                $paymentRequest->expires_at,
                ['paymentRequest' => $paymentRequest->public_id],
            ))->with('payment_error', 'We could not start the secure AFS payment right now. Please try again in a few moments, or contact us if the problem continues.');
        }

        if ($payments->gateway() instanceof RendersCheckoutWidget) {
            return redirect()->to(URL::temporarySignedRoute('pay.checkout', now()->addMinutes(35), ['payment' => $payment->id]));
        }

        return redirect()->away($checkout->url);
    }
}

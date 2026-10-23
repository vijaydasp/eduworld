<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\Payments\InvalidPaymentWebhook;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(PaymentManager $payments): View
    {
        return view('payments.create', ['gatewayAvailable' => $payments->gateway()->available()]);
    }

    public function store(StorePaymentRequest $request, PaymentManager $payments): RedirectResponse
    {
        $payment = $payments->createIntent($request->validated());
        $checkout = $payments->checkout($payment);

        return $checkout ? redirect()->away($checkout->url) : redirect($payments->receiptUrl($payment));
    }

    public function receipt(Payment $payment): View
    {
        return view('payments.receipt', compact('payment'));
    }

    public function webhook(Request $request, string $gateway, PaymentManager $payments): JsonResponse
    {
        try {
            $payments->processWebhook($gateway, $request);
        } catch (InvalidPaymentWebhook) {
            abort(403, 'Payment notification could not be verified.');
        }

        return response()->json(['received' => true]);
    }
}

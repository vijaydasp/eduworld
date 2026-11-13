<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStandalonePaymentRequest;
use App\Models\PaymentRequest;
use App\Services\Payments\PaymentRequestManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StandalonePaymentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(PaymentRequest::STATUSES)],
        ]);

        $requests = PaymentRequest::query()
            ->standaloneRequests()
            ->with(['payment', 'createdBy'])
            ->when($filters['q'] ?? null, function ($query, string $keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    foreach (['public_id', 'customer_name', 'customer_email', 'customer_phone', 'description'] as $field) {
                        $query->orWhere($field, 'like', '%'.$keyword.'%');
                    }
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.requests.index', compact('requests'));
    }

    public function create(): View
    {
        return view('admin.payments.requests.create');
    }

    public function store(StoreStandalonePaymentRequest $request, PaymentRequestManager $manager): RedirectResponse
    {
        $paymentRequest = $manager->createStandalone($request->validated(), $request->user());
        $sent = $paymentRequest->sent_at !== null;

        return redirect()
            ->route('admin.payments.requests.show', $paymentRequest)
            ->with($sent ? 'success' : 'error', $sent
                ? 'Standalone payment request for BHD '.number_format((float) $paymentRequest->amount, 3).' was emailed to '.$paymentRequest->customer_email.'.'
                : 'The payment request was saved, but the email could not be sent. Use Resend payment email to try again.');
    }

    public function show(PaymentRequest $paymentRequest): View
    {
        $this->ensureStandalone($paymentRequest);
        $paymentRequest->load(['payment', 'createdBy']);

        return view('admin.payments.requests.show', compact('paymentRequest'));
    }

    public function resend(PaymentRequest $paymentRequest, PaymentRequestManager $manager): RedirectResponse
    {
        $this->ensureStandalone($paymentRequest);
        $manager->expireIfNeeded($paymentRequest);

        if (! $paymentRequest->isPayable()) {
            return redirect()
                ->route('admin.payments.requests.show', $paymentRequest)
                ->with('error', 'This payment request is no longer active. Create a new request instead of resending an expired link.');
        }

        $sent = $manager->send($paymentRequest);

        return redirect()
            ->route('admin.payments.requests.show', $paymentRequest)
            ->with($sent ? 'success' : 'error', $sent
                ? 'Payment request email resent to '.$paymentRequest->customer_email.'.'
                : 'The payment request email could not be sent. Please try again.');
    }

    public function cancel(PaymentRequest $paymentRequest, PaymentRequestManager $manager): RedirectResponse
    {
        $this->ensureStandalone($paymentRequest);
        $manager->cancel($paymentRequest);

        return redirect()
            ->route('admin.payments.requests.show', $paymentRequest)
            ->with('success', 'The unpaid standalone payment request was cancelled. Its link can no longer start a new payment.');
    }

    private function ensureStandalone(PaymentRequest $paymentRequest): void
    {
        abort_unless($paymentRequest->isStandalone(), 404);
    }
}

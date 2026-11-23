<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStandalonePaymentRequest;
use App\Models\PaymentRequest;
use App\Rules\FutureKolkataDateTime;
use App\Services\Payments\PaymentRequestManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function show(PaymentRequest $paymentRequest, PaymentRequestManager $manager): View
    {
        $this->ensureStandalone($paymentRequest);
        $manager->expireIfNeeded($paymentRequest);
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

    public function extend(PaymentRequest $paymentRequest, Request $request, PaymentRequestManager $manager): RedirectResponse
    {
        $this->ensureStandalone($paymentRequest);
        $data = $this->validateExpiry($request);
        $updated = $manager->extend($paymentRequest, $data, $request->user());
        $sent = $manager->send($updated);

        return redirect()
            ->route('admin.payments.requests.show', $paymentRequest)
            ->with($sent ? 'success' : 'error', $sent
                ? 'The payment link expiry was extended and the updated link was emailed.'
                : 'The expiry was extended, but the updated email could not be sent.');
    }

    public function replace(PaymentRequest $paymentRequest, Request $request, PaymentRequestManager $manager): RedirectResponse
    {
        $this->ensureStandalone($paymentRequest);
        $data = $this->validateExpiry($request);
        $replacement = $manager->replace($paymentRequest, $data, $request->user());
        $sent = $manager->send($replacement);

        return redirect()
            ->route('admin.payments.requests.show', $replacement)
            ->with($sent ? 'success' : 'error', $sent
                ? 'A replacement payment link was created and emailed.'
                : 'The replacement request was created, but the email could not be sent.');
    }

    private function validateExpiry(Request $request): array
    {
        $data = $request->validate([
            'expiry_option' => ['required', Rule::in(['1_hour', '6_hours', '12_hours', '24_hours', '2_days', '3_days', '7_days', 'custom'])],
            'custom_expires_at' => ['nullable', 'required_if:expiry_option,custom', 'date_format:Y-m-d\TH:i', new FutureKolkataDateTime],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        if (! empty($data['due_at']) && ! empty($data['custom_expires_at']) && $data['expiry_option'] === 'custom') {
            $timezone = (string) config('payments.display_timezone', 'Asia/Kolkata');
            $due = Carbon::createFromFormat('Y-m-d\TH:i', $data['due_at'], $timezone);
            $expires = Carbon::createFromFormat('Y-m-d\TH:i', $data['custom_expires_at'], $timezone);
            if ($due->greaterThan($expires)) {
                throw ValidationException::withMessages(['due_at' => 'The payment due date cannot be later than the link expiry.']);
            }
        }

        return $data;
    }

    private function ensureStandalone(PaymentRequest $paymentRequest): void
    {
        abort_unless($paymentRequest->isStandalone(), 404);
    }
}

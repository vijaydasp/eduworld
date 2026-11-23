<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationFeeRequest;
use App\Models\Application;
use App\Models\PaymentRequest;
use App\Rules\FutureKolkataDateTime;
use App\Services\Payments\PaymentRequestManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationPaymentRequestController extends Controller
{
    public function store(StoreApplicationFeeRequest $request, Application $application, PaymentRequestManager $manager): RedirectResponse
    {
        $paymentRequest = $manager->create($application, $request->validated(), $request->user());
        $sent = $manager->send($paymentRequest);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with($sent ? 'success' : 'error', $sent
                ? 'Payment request for BHD '.number_format((float) $paymentRequest->amount, 3).' was emailed to '.$application->email.'.'
                : 'The payment request was saved, but the email could not be sent. Use Resend payment email to try again.');
    }

    public function resend(Application $application, PaymentRequest $paymentRequest, PaymentRequestManager $manager): RedirectResponse
    {
        abort_unless($paymentRequest->application_id === $application->id, 404);
        $manager->expireIfNeeded($paymentRequest);
        abort_unless($paymentRequest->isPayable(), 409, 'Only an active payment request can be resent.');

        $sent = $manager->send($paymentRequest);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with($sent ? 'success' : 'error', $sent
                ? 'Payment request email resent to '.$application->email.'.'
                : 'The payment request email could not be sent. Please try again.');
    }

    public function cancel(Application $application, PaymentRequest $paymentRequest, PaymentRequestManager $manager): RedirectResponse
    {
        abort_unless($paymentRequest->application_id === $application->id, 404);
        $manager->cancel($paymentRequest);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'The unpaid payment request was cancelled. Its link can no longer start a payment.');
    }

    public function extend(Application $application, PaymentRequest $paymentRequest, Request $request, PaymentRequestManager $manager): RedirectResponse
    {
        abort_unless($paymentRequest->application_id === $application->id, 404);
        $data = $this->validateExpiry($request);
        $updated = $manager->extend($paymentRequest, $data, $request->user());
        $sent = $manager->send($updated);

        return redirect()->route('admin.applications.show', $application)
            ->with($sent ? 'success' : 'error', $sent
                ? 'The payment link expiry was extended and the updated link was emailed.'
                : 'The expiry was extended, but the updated email could not be sent.');
    }

    public function replace(Application $application, PaymentRequest $paymentRequest, Request $request, PaymentRequestManager $manager): RedirectResponse
    {
        abort_unless($paymentRequest->application_id === $application->id, 404);
        $data = $this->validateExpiry($request);
        $replacement = $manager->replace($paymentRequest, $data, $request->user());
        $sent = $manager->send($replacement);

        return redirect()->route('admin.applications.show', $application)
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

        if (! empty($data['due_at']) && $data['expiry_option'] === 'custom' && ! empty($data['custom_expires_at'])) {
            $timezone = (string) config('payments.display_timezone', 'Asia/Kolkata');
            $due = Carbon::createFromFormat('Y-m-d\TH:i', $data['due_at'], $timezone);
            $expires = Carbon::createFromFormat('Y-m-d\TH:i', $data['custom_expires_at'], $timezone);
            if ($due->greaterThan($expires)) {
                throw ValidationException::withMessages(['due_at' => 'The payment due date cannot be later than the link expiry.']);
            }
        }

        return $data;
    }
}

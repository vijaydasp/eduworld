<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationFeeRequest;
use App\Models\Application;
use App\Models\PaymentRequest;
use App\Services\Payments\PaymentRequestManager;
use Illuminate\Http\RedirectResponse;

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
        abort_unless($paymentRequest->status === 'sent', 409, 'Only an active payment request can be resent.');

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
}

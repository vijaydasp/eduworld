<?php

namespace App\Services\Payments;

use App\Models\Application;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Notifications\PaymentRequestReady;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentRequestManager
{
    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * Create a fee request for the application currently open in the admin panel.
     * An existing unpaid request must be explicitly replaced so duplicates are not
     * created by an accidental double submission.
     *
     * @param  array{amount: string, description: string, validity_days: int, replace_existing?: bool}  $data
     */
    public function create(Application $application, array $data, User $user): PaymentRequest
    {
        return DB::transaction(function () use ($application, $data, $user): PaymentRequest {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->payment_status === 'paid' || $locked->payments()->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'This application already has a successful payment. Create no further payment request.',
                ]);
            }

            $locked->paymentRequests()
                ->where('status', 'sent')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $active = $locked->paymentRequests()
                ->where('status', 'sent')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->get();

            if ($active->isNotEmpty() && ! ($data['replace_existing'] ?? false)) {
                throw ValidationException::withMessages([
                    'replace_existing' => 'An active payment request already exists for this application. Tick the replacement box to cancel it and create a new request.',
                ]);
            }

            foreach ($active as $previous) {
                $before = $previous->only(['status']);
                $previous->update(['status' => 'cancelled']);
                Audit::record('payment_request.cancelled', $previous, $before, ['status' => 'cancelled', 'reason' => 'replaced']);
            }

            $paymentRequest = $locked->paymentRequests()->create([
                'type' => PaymentRequest::TYPE_APPLICATION,
                'amount' => BhdAmount::normalize((string) $data['amount']),
                'currency' => 'BHD',
                'description' => $data['description'],
                'status' => 'sent',
                'expires_at' => now()->addDays(max(1, (int) ($data['validity_days'] ?? 7))),
                'created_by' => $user->id,
            ]);

            Audit::record('payment_request.created', $paymentRequest, [], $paymentRequest->only([
                'type', 'application_id', 'amount', 'currency', 'description', 'status', 'expires_at',
            ]));

            return $paymentRequest;
        }, 5);
    }

    /**
     * Create and email an administrator-only request that is not linked to an application.
     *
     * @param  array{customer_name: string, customer_email: string, customer_phone?: ?string, amount: string, description: string, validity_days?: int}  $data
     */
    public function createStandalone(array $data, User $user): PaymentRequest
    {
        $paymentRequest = DB::transaction(function () use ($data, $user): PaymentRequest {
            $paymentRequest = PaymentRequest::create([
                'type' => PaymentRequest::TYPE_STANDALONE,
                'application_id' => null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => filled($data['customer_phone'] ?? null) ? (string) $data['customer_phone'] : null,
                'amount' => BhdAmount::normalize((string) $data['amount']),
                'currency' => 'BHD',
                'description' => $data['description'],
                'status' => 'sent',
                'expires_at' => now()->addDays(max(1, (int) ($data['validity_days'] ?? 7))),
                'created_by' => $user->id,
            ]);

            Audit::record('payment_request.created', $paymentRequest, [], $paymentRequest->only([
                'type', 'application_id', 'customer_name', 'customer_email', 'amount',
                'currency', 'description', 'status', 'expires_at',
            ]));

            return $paymentRequest;
        }, 5);

        $this->send($paymentRequest);

        return $paymentRequest;
    }

    /**
     * Send the request email. A queued notification is only dispatched here; the
     * request record stays available for an administrator to resend if dispatch fails.
     */
    public function send(PaymentRequest $paymentRequest): bool
    {
        $this->expireIfNeeded($paymentRequest);

        if ($paymentRequest->status !== 'sent') {
            return false;
        }

        $paymentRequest->loadMissing('application');
        $email = $paymentRequest->recipientEmail();

        if ($email === null) {
            return false;
        }

        try {
            Notification::route('mail', $email)->notify(new PaymentRequestReady($paymentRequest));
        } catch (Throwable $error) {
            report($error);

            return false;
        }

        $paymentRequest->update(['sent_at' => now()]);
        Audit::record('payment_request.sent', $paymentRequest, [], [
            'email' => $email,
            'sent_at' => $paymentRequest->sent_at?->toIso8601String(),
        ]);

        return true;
    }

    public function cancel(PaymentRequest $paymentRequest): void
    {
        DB::transaction(function () use ($paymentRequest): void {
            $locked = PaymentRequest::query()->with('payment')->lockForUpdate()->findOrFail($paymentRequest->id);
            abort_unless($locked->status === 'sent', 409, 'Only an unpaid payment request can be cancelled.');
            abort_if(
                $locked->payment && in_array($locked->payment->status, ['paid', 'partially_paid', 'refunded'], true),
                409,
                'A settled or partially settled payment cannot be cancelled. Reconcile the payment instead.',
            );

            $before = $locked->only(['status']);
            $locked->update(['status' => 'cancelled']);
            Audit::record('payment_request.cancelled', $locked, $before, [
                'status' => 'cancelled',
                'payment_status' => $locked->payment?->status,
            ]);
        });
    }

    public function expireIfNeeded(PaymentRequest $paymentRequest): void
    {
        if ($paymentRequest->status !== 'sent' || $paymentRequest->expires_at === null || ! $paymentRequest->expires_at->isPast()) {
            return;
        }

        $before = $paymentRequest->only(['status']);
        $paymentRequest->update(['status' => 'expired']);
        Audit::record('payment_request.expired', $paymentRequest, $before, ['status' => 'expired']);
    }

    /**
     * Create or reuse the single payment attempt attached to a request. Amounts and
     * customer details always come from the stored request, never the browser.
     */
    public function start(PaymentRequest $paymentRequest): Payment
    {
        $this->expireIfNeeded($paymentRequest);

        abort_unless($paymentRequest->status === 'sent', 410, 'This payment request is no longer available.');
        abort_unless($paymentRequest->isPayable(), 410, 'This payment request has expired.');

        return DB::transaction(function () use ($paymentRequest): Payment {
            $locked = PaymentRequest::query()->lockForUpdate()->findOrFail($paymentRequest->id);
            abort_unless($locked->status === 'sent' && $locked->isPayable(), 410, 'This payment request is no longer available.');

            $gatewayName = $this->payments->gateway()->name();
            $existing = $locked->payment;

            if ($existing?->status === 'paid') {
                abort(409, 'This payment request has already been paid.');
            }

            if ($existing && $existing->status === 'pending' && $existing->channel === 'gateway') {
                if (! hash_equals((string) $existing->gateway, $gatewayName)) {
                    $existing->update(['gateway' => $gatewayName, 'gateway_reference' => null]);
                }

                return $existing;
            }

            if ($locked->isStandalone()) {
                abort_if($locked->application_id !== null, 409, 'A standalone payment request cannot be linked to an application.');

                $payment = $this->payments->createIntent([
                    'application_id' => null,
                    'customer_name' => $locked->customer_name ?: 'Customer',
                    'phone' => (string) ($locked->customer_phone ?? ''),
                    'email' => $locked->customer_email,
                    'entered_reference' => null,
                    'service' => 'other',
                    'amount' => (string) $locked->amount,
                    'remarks' => $locked->description,
                ]);
            } else {
                abort_if($locked->application_id === null, 409, 'An application payment request must be linked to an application.');

                $application = Application::query()->findOrFail($locked->application_id);

                $payment = $this->payments->createIntent([
                    'application_id' => $application->id,
                    'customer_name' => $application->full_name,
                    'phone' => $application->phone,
                    'email' => $application->email,
                    'entered_reference' => $application->reference_number,
                    'service' => $application->service_type,
                    'amount' => (string) $locked->amount,
                    'remarks' => $locked->description,
                ]);
            }

            $locked->update(['payment_id' => $payment->id]);
            Audit::record('payment_request.payment_started', $locked, [], $locked->only(['payment_id', 'type', 'amount', 'currency', 'status']));

            return $payment;
        }, 5);
    }
}

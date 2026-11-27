<?php

namespace App\Services\Payments;

use App\Models\Application;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentRequest;
use App\Notifications\PaymentConfirmed;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentManager
{
    public function gateway(): PaymentGatewayInterface
    {
        $driver = config('payments.driver', 'disabled');
        $adapter = config('payments.gateways.'.$driver, DisabledGateway::class);
        $gateway = app($adapter);

        return $gateway instanceof PaymentGatewayInterface ? $gateway : new DisabledGateway;
    }

    public function createIntent(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'application_id' => $data['application_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'entered_reference' => $data['entered_reference'] ?? null,
                'service' => $data['service'],
                'amount' => BhdAmount::normalize((string) $data['amount']),
                'currency' => 'BHD',
                'remarks' => $data['remarks'] ?? null,
                'gateway' => $this->gateway()->name(),
                'channel' => 'gateway',
                'status' => 'pending',
                'amount_received' => '0.000',
            ]);

            $payment->update(['transaction_reference' => 'EWP-'.now()->year.'-'.str_pad((string) $payment->id, 7, '0', STR_PAD_LEFT)]);

            return $payment;
        });
    }

    public function checkout(Payment $payment): ?GatewayCheckout
    {
        $gateway = $this->gateway();

        if (! $gateway->available()) {
            return null;
        }

        return DB::transaction(function () use ($payment, $gateway): ?GatewayCheckout {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->setRawAttributes($locked->getAttributes(), true);

            if ($payment->status !== 'pending' || $payment->channel !== 'gateway') {
                return null;
            }

            $paymentRequest = PaymentRequest::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($paymentRequest) {
                if ($paymentRequest->status === 'sent' && ! $paymentRequest->expires_at?->isFuture()) {
                    $before = $paymentRequest->only(['status']);
                    $paymentRequest->update(['status' => 'expired']);
                    Audit::record('payment_request.expired', $paymentRequest, $before, ['status' => 'expired']);
                }

                if ($paymentRequest->status !== 'sent' || ! $paymentRequest->expires_at?->isFuture()) {
                    return null;
                }
            }

            if ($resumed = $this->resumeExistingCheckout($payment, $gateway)) {
                return $resumed;
            }

            try {
                $checkout = $gateway->initiate($payment, $this->returnUrl($payment));
            } catch (Throwable $exception) {
                // Do not leak provider request bodies, credentials or card data into logs.
                Log::warning('Payment checkout could not be started.', [
                    'payment_id' => $payment->id,
                    'gateway' => $gateway->name(),
                    'exception' => $exception::class,
                    'message' => mb_substr(str_replace(["\r", "\n"], ' ', $exception->getMessage()), 0, 300),
                ]);

                return null;
            }

            if (! in_array(parse_url($checkout->url, PHP_URL_HOST), config('payments.checkout_hosts', []), true)) {
                Log::warning('Payment gateway returned a checkout host that is not approved.', ['payment_id' => $payment->id]);

                return null;
            }

            $this->rememberCheckoutSession($payment, $checkout->reference, $checkout->integrity);

            return $checkout;
        }, 3);
    }

    /**
     * Reuse a provider checkout that was created recently instead of replacing its
     * gateway reference. This keeps repeated Pay clicks and browser retries on the
     * same session while still allowing an expired session to be reinitialised.
     */
    private function resumeExistingCheckout(Payment $payment, PaymentGatewayInterface $gateway): ?GatewayCheckout
    {
        if (! $gateway instanceof RendersCheckoutWidget
            || ! $payment->gateway_reference
            || ! hash_equals($gateway->name(), (string) $payment->gateway)) {
            return null;
        }

        $startedAt = data_get($payment->gateway_response, 'checkout_started_at');
        $started = is_string($startedAt) && $startedAt !== ''
            ? rescue(fn () => Carbon::parse($startedAt), null, false)
            : null;
        $started ??= $payment->updated_at;

        $reuseMinutes = max(1, (int) config('payments.checkout_reuse_minutes', 25));

        if (! $started instanceof Carbon || $started->lt(now()->subMinutes($reuseMinutes))) {
            return null;
        }

        try {
            return $gateway->resumeCheckout($payment);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Record checkout metadata so a repeated POST can resume the same provider session.
     * Older references are kept for settlement in case a customer completes the previous
     * widget session while a new one is being created.
     */
    private function rememberCheckoutSession(Payment $payment, string $reference, ?string $integrity): void
    {
        $response = (array) ($payment->gateway_response ?? []);
        $previous = array_values(array_unique(array_filter(
            array_merge((array) ($response['previous_checkout_references'] ?? []), [$payment->gateway_reference]),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        )));

        $response['checkout_started_at'] = now()->toIso8601String();
        $response['environment'] = (string) config('payments.environment', 'test');
        $response['checkout_payment_type'] = (string) config('payments.default_type', 'DB');
        $response['previous_checkout_references'] = $previous;
        if ($integrity !== null) {
            $response['checkout_integrity'] = $integrity;
        } else {
            unset($response['checkout_integrity']);
        }

        $payment->update([
            'gateway_reference' => $reference,
            'gateway_response' => $response,
        ]);
    }

    public function receiptUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('pay.receipt', now()->addDay(), ['payment' => $payment->id]);
    }

    public function returnUrl(Payment $payment): string
    {
        return route('pay.return', ['reference' => $payment->transaction_reference]);
    }

    public function processWebhook(string $gatewayName, Request $request): bool
    {
        $gateway = $this->gateway();
        abort_unless($gateway->available(), 503, 'Online payments are not configured.');
        abort_unless(hash_equals($gateway->name(), $gatewayName), 404);

        // This is the sole entry point for external settlement. Verification happens
        // before looking up a payment, taking a lock or writing any event.
        $event = $gateway->verifyWebhook($request);

        return $this->settle($gateway, $event);
    }

    /**
     * Reconcile the shopper's browser return by querying the provider. The browser
     * parameters are lookup hints only; the gateway decides whether a verified event exists.
     */
    public function reconcileCheckout(Payment $payment, Request $request): bool
    {
        $gateway = $this->gateway();

        if (! $gateway->available() || ! $gateway instanceof VerifiesCheckoutReturn) {
            return false;
        }

        if ($payment->channel !== 'gateway'
            || ! hash_equals($gateway->name(), (string) $payment->gateway)
            || $payment->status !== 'pending'
            || ! $payment->gateway_reference) {
            return false;
        }

        try {
            $event = $gateway->verifyCheckoutReturn($payment, $request);
        } catch (Throwable $exception) {
            Log::warning('Payment return could not be verified.', [
                'payment_id' => $payment->id,
                'gateway' => $gateway->name(),
                'exception' => $exception::class,
                'message' => mb_substr(str_replace(["\r", "\n"], ' ', $exception->getMessage()), 0, 300),
            ]);

            return false;
        }

        return $event !== null && $this->settle($gateway, $event);
    }

    private function settle(PaymentGatewayInterface $gateway, VerifiedPaymentEvent $event): bool
    {
        return DB::transaction(function () use ($gateway, $event) {
            $payment = Payment::where('transaction_reference', $event->transactionReference)->lockForUpdate()->firstOrFail();
            abort_unless($payment->channel === 'gateway' && hash_equals($payment->gateway, $gateway->name()), 409, 'Payment provider does not match.');

            $knownReferences = array_merge(
                [(string) $payment->gateway_reference],
                (array) data_get($payment->gateway_response, 'previous_checkout_references', []),
            );
            $sessionMatches = collect($knownReferences)->contains(
                fn (mixed $reference): bool => is_string($reference) && $reference !== '' && hash_equals($reference, $event->gatewayReference),
            );
            abort_unless($payment->gateway_reference && $sessionMatches, 409, 'Payment session does not match.');

            $previous = PaymentEvent::where('gateway', $gateway->name())->where('event_id', $event->eventId)->first();
            if ($previous) {
                abort_unless($previous->payment_id === $payment->id && hash_equals($previous->fingerprint, $event->fingerprint()), 409, 'Event identifier was already used.');

                return false;
            }

            $expected = BhdAmount::minorUnits(BhdAmount::gatewayAmount((string) $payment->amount));
            $received = BhdAmount::minorUnits(BhdAmount::gatewayAmount($event->amountReceived));
            abort_unless($event->currency === 'BHD' && $payment->currency === $event->currency && BhdAmount::minorUnits(BhdAmount::gatewayAmount($event->amount)) === $expected, 422, 'Payment amount or currency does not match.');
            abort_unless(match ($event->status) {
                'paid' => $received === $expected,
                'partially_paid' => $received > 0 && $received < $expected,
                'failed', 'refunded' => $received === 0,
            }, 422, 'Settlement amount does not match the status.');

            $before = $payment->only(['status', 'amount_received', 'paid_at']);
            // Out-of-order callbacks must never undo settled money. Refunds are
            // accepted only after a known settlement and represent a full refund.
            $ignore = $payment->status === 'refunded'
                || ($payment->status === 'paid' && $event->status !== 'refunded')
                || ($payment->status === 'partially_paid' && ($event->status === 'failed'
                    || ($event->status === 'partially_paid' && $received <= BhdAmount::minorUnits($payment->amount_received))));

            if ($event->status === 'refunded' && ! in_array($payment->status, ['paid', 'partially_paid', 'refunded'], true)) {
                abort(409, 'Refund arrived before a verified settlement; retry after settlement.');
            }

            PaymentEvent::create([
                'payment_id' => $payment->id, 'gateway' => $gateway->name(),
                'event_id' => $event->eventId, 'fingerprint' => $event->fingerprint(),
                'status' => $event->status, 'result' => $ignore ? 'ignored_stale' : 'applied',
            ]);

            if ($ignore) {
                return false;
            }

            $providerReference = is_array($payment->gateway_response)
                ? ($payment->gateway_response['provider_reference'] ?? null)
                : null;

            $payment->update([
                'status' => $event->status,
                'amount_received' => BhdAmount::normalize($event->amountReceived),
                'paid_at' => $event->status === 'paid' ? now() : $payment->paid_at,
                'refunded_at' => $event->status === 'refunded' ? now() : null,
                'provider_payment_type' => $event->paymentType,
                'provider_payment_brand' => $event->paymentBrand,
                'provider_result_code' => $event->resultCode,
                'provider_transaction_at' => $event->transactionTimestamp
                    ? Carbon::parse($event->transactionTimestamp)
                    : null,
                'gateway_response' => array_filter([
                    'event_id' => $event->eventId,
                    'provider_reference' => $providerReference ?? $event->providerReference,
                    'verified_at' => now()->toIso8601String(),
                    'status' => $event->status,
                    'provider_payment_type' => $event->paymentType,
                    'provider_payment_brand' => $event->paymentBrand,
                    'provider_result_code' => $event->resultCode,
                    'provider_result_description' => $event->resultDescription,
                    'provider_entity_id' => $event->providerEntityId,
                    'provider_transaction_at' => $event->transactionTimestamp,
                    'three_d_secure' => $event->threeDSecureDetails !== [] ? $event->threeDSecureDetails : null,
                ], fn (mixed $value): bool => $value !== null),
            ]);
            $this->syncApplication($payment->application_id);
            $this->markPaymentRequestPaid($payment, $event->status);
            Audit::record('payment.gateway_verified', $payment, $before, $payment->only(['status', 'amount_received', 'paid_at']));

            if ($event->status === 'paid') {
                $this->notify($payment);
            }

            return true;
        }, 3);
    }

    public function recordOffline(array $data): Payment
    {
        Gate::authorize('manage-settings');

        return DB::transaction(function () use ($data) {
            $application = ! empty($data['application_reference'])
                ? Application::where('reference_number', $data['application_reference'])->firstOrFail()
                : null;
            $received = BhdAmount::minorUnits((string) $data['amount_received']);
            $amount = BhdAmount::minorUnits((string) $data['amount']);
            if ($received < 1 || $received > $amount) {
                throw ValidationException::withMessages(['amount_received' => 'Received amount must be greater than zero and no more than the expected amount.']);
            }

            $payment = $this->createIntent($data);
            $payment->update([
                'application_id' => $application?->id,
                'gateway' => 'offline', 'channel' => 'offline',
                'offline_reference' => $data['offline_reference'],
                'recorded_by' => auth()->id(),
                'amount_received' => BhdAmount::normalize((string) $data['amount_received']),
                'status' => $amount === $received ? 'paid' : 'partially_paid',
                'paid_at' => $amount === $received ? now() : null,
            ]);
            $this->syncApplication($payment->application_id);
            Audit::record('payment.offline_recorded', $payment, [], $payment->only(['status', 'channel', 'amount', 'amount_received', 'offline_reference', 'application_id']));
            if ($payment->status === 'paid') {
                $this->notify($payment);
            }

            return $payment;
        });
    }

    public function refundOffline(Payment $payment, string $reason): void
    {
        Gate::authorize('manage-settings');
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->channel === 'offline' && in_array($payment->status, ['paid', 'partially_paid'], true), 409, 'Only settled offline receipts can be refunded here.');
            $before = $payment->only(['status', 'amount_received']);
            $payment->update(['status' => 'refunded', 'amount_received' => '0.000', 'refunded_at' => now()]);
            $this->syncApplication($payment->application_id);
            Audit::record('payment.offline_refunded', $payment, $before, ['status' => 'refunded', 'reason' => $reason]);
        });
    }

    private function syncApplication(?int $applicationId): void
    {
        if (! $applicationId) {
            return;
        }

        $application = Application::whereKey($applicationId)->lockForUpdate()->first();
        if (! $application) {
            return;
        }
        $statuses = $application->payments()->pluck('status');
        $status = match (true) {
            $statuses->isEmpty() => 'pending',
            $statuses->contains('partially_paid') => 'partially_paid',
            $statuses->contains('paid') && $statuses->contains('pending') => 'partially_paid',
            $statuses->contains('paid') => 'paid',
            $statuses->contains('pending') => 'pending',
            $statuses->contains('refunded') => 'refunded',
            default => 'failed',
        };
        if ($application->payment_status !== $status) {
            $before = $application->payment_status;
            $application->update(['payment_status' => $status]);
            Audit::record('application.payment_status_changed', $application, ['payment_status' => $before], ['payment_status' => $status]);
        }
    }

    private function notify(Payment $payment): void
    {
        if ($payment->email) {
            Notification::route('mail', $payment->email)->notify((new PaymentConfirmed($payment))->afterCommit());
        }
    }

    private function markPaymentRequestPaid(Payment $payment, string $eventStatus): void
    {
        if ($eventStatus !== 'paid') {
            return;
        }

        $paymentRequest = PaymentRequest::where('payment_id', $payment->id)->lockForUpdate()->first();

        if (! $paymentRequest || $paymentRequest->status === 'paid') {
            return;
        }

        $before = $paymentRequest->only(['status', 'paid_at']);
        $paymentRequest->update(['status' => 'paid', 'paid_at' => now()]);
        Audit::record('payment_request.paid', $paymentRequest, $before, $paymentRequest->only(['status', 'paid_at']));
    }
}

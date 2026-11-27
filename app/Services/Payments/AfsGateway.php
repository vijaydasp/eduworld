<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * AFS (Arab Financial Services) hosted checkout using the OPPWA COPYandPAY widget.
 *
 * The checkout session is created server-to-server, then the widget renders the card form
 * on our own page. Settlement is never derived from browser fields: webhooks are decrypted
 * and independently re-checked with the payment status API, and the shopper return performs
 * the same server-to-server lookup.
 *
 * @see https://afs.docs.oppwa.com/integrations/widget
 */
final class AfsGateway implements PaymentGatewayInterface, RendersCheckoutWidget, VerifiesCheckoutReturn
{
    private const PAYMENT_ID_PATTERN = '/\A[a-zA-Z0-9]{32}\z/';

    private const CHECKOUT_ID_PATTERN = '/\A[a-zA-Z0-9.\-]{32,48}\z/';

    private const RETURN_PATH_PATTERN = '#\A/v1/checkouts/([a-zA-Z0-9.\-]{32,48})/payment\z#';

    public function __construct(private readonly AfsWebhookDecoder $decoder) {}

    public function name(): string
    {
        return 'afs';
    }

    public function available(): bool
    {
        $baseUrl = $this->baseUrl();
        $environment = (string) config('payments.environment', 'test');
        $expectedHost = match ($environment) {
            'test' => 'eu-test.oppwa.com',
            'live' => 'eu-prod.oppwa.com',
            default => null,
        };

        return $this->entityId() !== null
            && $this->accessToken() !== null
            && $baseUrl !== null
            && (string) config('payments.currency') === 'BHD'
            && (string) config('payments.default_type') === 'DB'
            && $expectedHost !== null
            && hash_equals($expectedHost, (string) parse_url($baseUrl, PHP_URL_HOST))
            && in_array((string) parse_url($baseUrl, PHP_URL_HOST), (array) config('payments.checkout_hosts', []), true);
    }

    public function initiate(Payment $payment, string $returnUrl): GatewayCheckout
    {
        $baseUrl = $this->requiredBaseUrl();
        $entityId = $this->requiredEntityId();
        $accessToken = $this->requiredAccessToken();

        $response = Http::withToken($accessToken)
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->post($this->endpoint('v1/checkouts'), $this->checkoutParameters($payment, $returnUrl, $entityId));

        if (! $response->successful()) {
            throw new RuntimeException('The AFS checkout request was rejected.');
        }

        $data = $response->json();
        $checkoutId = is_array($data) ? ($data['id'] ?? null) : null;
        $resultCode = is_array($data) ? ($data['result']['code'] ?? null) : null;
        $integrity = is_array($data) ? ($data['integrity'] ?? null) : null;

        if (! is_string($checkoutId)
            || preg_match(self::CHECKOUT_ID_PATTERN, $checkoutId) !== 1
            || ! AfsResultCode::isCheckoutCreated(is_string($resultCode) ? $resultCode : null)
            || (config('payments.afs.widget_integrity') && ! $this->validIntegrity($integrity))) {
            throw new RuntimeException('AFS did not return a usable checkout session.');
        }

        return new GatewayCheckout($baseUrl, $checkoutId, $this->validIntegrity($integrity) ? $integrity : null);
    }

    public function widgetScriptUrl(string $checkoutReference): string
    {
        if (preg_match(self::CHECKOUT_ID_PATTERN, $checkoutReference) !== 1) {
            throw new InvalidArgumentException('The AFS checkout reference is invalid.');
        }

        return $this->endpoint('v1/paymentWidgets.js').'?checkoutId='.rawurlencode($checkoutReference);
    }

    public function resumeCheckout(Payment $payment): GatewayCheckout
    {
        $reference = (string) $payment->gateway_reference;

        if (preg_match(self::CHECKOUT_ID_PATTERN, $reference) !== 1) {
            throw new InvalidArgumentException('The AFS checkout reference is invalid.');
        }

        $integrity = data_get($payment->gateway_response, 'checkout_integrity');

        if (config('payments.afs.widget_integrity') && ! $this->validIntegrity($integrity)) {
            throw new InvalidArgumentException('The existing AFS checkout has no valid integrity value.');
        }

        return new GatewayCheckout(
            $this->requiredBaseUrl(),
            $reference,
            $this->validIntegrity($integrity) ? $integrity : null,
        );
    }

    public function verifyWebhook(Request $request): VerifiedPaymentEvent
    {
        $notification = $this->decoder->decode($request);

        if (($notification['type'] ?? null) !== 'PAYMENT') {
            throw new InvalidPaymentWebhook('The AFS notification is not a payment event.');
        }

        $payload = $notification['payload'] ?? null;
        $paymentId = is_array($payload) ? ($payload['id'] ?? null) : null;

        if (! is_string($paymentId) || preg_match(self::PAYMENT_ID_PATTERN, $paymentId) !== 1) {
            throw new InvalidPaymentWebhook('The AFS notification has no usable payment identifier.');
        }

        $this->assertEntity($notification, $payload);

        // Never settle from the notification body. Query the provider for the authoritative
        // amount, currency, entity, payment type and result code.
        return $this->eventFromStatus($this->paymentStatus(
            $paymentId,
            $this->checkoutReferenceForWebhook($payload),
        ));
    }

    public function verifyCheckoutReturn(Payment $payment, Request $request): ?VerifiedPaymentEvent
    {
        if (! hash_equals($this->name(), (string) $payment->gateway) || ! $payment->gateway_reference) {
            return null;
        }

        $resourcePath = $request->query('resourcePath');

        if (is_string($resourcePath) && $resourcePath !== '') {
            if (preg_match(self::RETURN_PATH_PATTERN, $resourcePath, $matches) !== 1
                || ! hash_equals($payment->gateway_reference, $matches[1])) {
                throw new InvalidPaymentWebhook('The AFS return path does not belong to this checkout.');
            }

            $status = $this->status(ltrim($resourcePath, '/'), allowMissing: true, checkoutReference: $payment->gateway_reference);

            return $status === null ? null : $this->checkoutEvent($status, $payment);
        }

        $paymentId = $request->query('id');

        if (is_string($paymentId) && preg_match(self::PAYMENT_ID_PATTERN, $paymentId) === 1) {
            $status = $this->paymentStatus($paymentId, (string) $payment->gateway_reference);

            if (($status['merchantTransactionId'] ?? null) !== $payment->transaction_reference) {
                return null;
            }

            return $this->checkoutEvent($status, $payment);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function eventFromStatus(array $status): VerifiedPaymentEvent
    {
        $classification = AfsResultCode::classify($this->resultCode($status));

        if (in_array($classification, [AfsResultCode::PENDING, AfsResultCode::REVIEW_REQUIRED], true)) {
            throw new DeferredPaymentWebhook('The AFS transaction is not in a final settlement state.');
        }

        $paymentType = strtoupper((string) ($status['paymentType'] ?? ''));

        if (in_array($paymentType, ['RF', 'RV'], true)) {
            return $this->refundEvent($status, $classification);
        }

        if ($paymentType !== 'DB') {
            throw new DeferredPaymentWebhook('The AFS payment type is not supported for settlement.');
        }

        $reference = (string) ($status['merchantTransactionId'] ?? '');
        $payment = Payment::where('transaction_reference', $reference)->first();

        if (! $payment
            || ! hash_equals($this->name(), (string) $payment->gateway)
            || ! $payment->gateway_reference) {
            throw new InvalidPaymentWebhook('The AFS transaction does not match a payment session.');
        }

        $this->assertSupportedBrand($status, $classification);

        return $this->settlementEvent($status, $payment, $classification);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function checkoutEvent(array $status, Payment $payment): ?VerifiedPaymentEvent
    {
        $classification = AfsResultCode::classify($this->resultCode($status));

        if (in_array($classification, [AfsResultCode::PENDING, AfsResultCode::REVIEW_REQUIRED], true)) {
            Log::info('COPYandPAY returned a non-final checkout result.', [
                'payment_id' => $payment->id,
                'result_code' => $this->resultCode($status),
                'result_description' => $this->resultDescription($status),
                'payment_type' => $this->providerString($status, 'paymentType'),
                'payment_brand' => $this->providerString($status, 'paymentBrand'),
                'three_d_secure' => $this->threeDSecureDetails($status),
            ]);

            return null;
        }

        $paymentType = strtoupper((string) ($status['paymentType'] ?? ''));

        if ($paymentType !== 'DB') {
            return null;
        }

        $reference = $status['merchantTransactionId'] ?? null;

        if (! is_string($reference) || ! hash_equals((string) $payment->transaction_reference, $reference)) {
            return null;
        }

        $this->assertSupportedBrand($status, $classification);

        return $this->settlementEvent($status, $payment, $classification);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function settlementEvent(array $status, Payment $payment, string $classification): VerifiedPaymentEvent
    {
        $amount = $this->amount($status);
        $currency = strtoupper((string) ($status['currency'] ?? ''));

        if ($currency === '') {
            throw new InvalidPaymentWebhook('The AFS transaction has no currency.');
        }

        return new VerifiedPaymentEvent(
            eventId: $this->eventId($status),
            transactionReference: (string) $payment->transaction_reference,
            gatewayReference: (string) $payment->gateway_reference,
            status: $classification,
            amount: $amount,
            amountReceived: $classification === AfsResultCode::PAID ? $amount : '0.000',
            currency: $currency,
            providerReference: $this->providerReference($status),
            paymentType: $this->providerString($status, 'paymentType'),
            paymentBrand: $this->providerString($status, 'paymentBrand'),
            resultCode: $this->resultCode($status),
            transactionTimestamp: $this->transactionTimestamp($status),
            resultDescription: $this->resultDescription($status),
            providerEntityId: $this->providerEntityId($status),
            threeDSecureDetails: $this->threeDSecureDetails($status),
        );
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function refundEvent(array $status, string $classification): VerifiedPaymentEvent
    {
        if ($classification !== AfsResultCode::PAID) {
            throw new DeferredPaymentWebhook('The AFS refund was not successful.');
        }

        if (strtoupper((string) ($status['paymentType'] ?? '')) === 'RV') {
            throw new DeferredPaymentWebhook('AFS reversals are not refunds of settled funds.');
        }

        $referencedId = $status['referencedId'] ?? null;
        $payment = is_string($referencedId)
            ? Payment::where('gateway', $this->name())->where('gateway_response->provider_reference', $referencedId)->first()
            : null;

        if (! $payment || ! $payment->gateway_reference) {
            throw new InvalidPaymentWebhook('The AFS refund does not reference a known payment.');
        }

        $refundAmount = $this->amount($status);
        $currency = strtoupper((string) ($status['currency'] ?? ''));

        if ($currency !== (string) $payment->currency) {
            throw new InvalidPaymentWebhook('The AFS refund currency does not match the original payment.');
        }

        if (BhdAmount::gatewayAmount($refundAmount) !== BhdAmount::gatewayAmount((string) $payment->amount)) {
            throw new DeferredPaymentWebhook('Only full refunds of settled funds are supported.');
        }

        return new VerifiedPaymentEvent(
            eventId: $this->eventId($status),
            transactionReference: (string) $payment->transaction_reference,
            gatewayReference: (string) $payment->gateway_reference,
            status: 'refunded',
            amount: (string) $payment->amount,
            amountReceived: '0.000',
            currency: $currency,
            providerReference: $this->providerReference($status),
            paymentType: $this->providerString($status, 'paymentType'),
            paymentBrand: $this->providerString($status, 'paymentBrand'),
            resultCode: $this->resultCode($status),
            transactionTimestamp: $this->transactionTimestamp($status),
            resultDescription: $this->resultDescription($status),
            providerEntityId: $this->providerEntityId($status),
            threeDSecureDetails: $this->threeDSecureDetails($status),
        );
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, mixed>  $payload
     */
    private function assertEntity(array $notification, array $payload): void
    {
        $configured = $this->requiredEntityId();
        $received = array_filter([
            $notification['authentication']['entityId'] ?? null,
            $payload['authentication']['entityId'] ?? null,
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        if ($received === []) {
            throw new InvalidPaymentWebhook('The AFS notification has no merchant entity.');
        }

        foreach ($received as $entityId) {
            if (! hash_equals($configured, (string) $entityId)) {
                throw new InvalidPaymentWebhook('The AFS notification belongs to another merchant entity.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function paymentStatus(string $paymentId, string $checkoutReference): array
    {
        $status = $this->status('v1/payments/'.rawurlencode($paymentId), allowMissing: false, checkoutReference: $checkoutReference);

        if ($status === null || ($status['id'] ?? null) !== $paymentId) {
            throw new InvalidPaymentWebhook('AFS returned an unexpected payment status.');
        }

        return $status;
    }

    /** @return array<string, mixed>|null */
    private function status(string $path, bool $allowMissing, string $checkoutReference): ?array
    {
        $rateLimitKey = 'afs-status:'.hash('sha256', $checkoutReference);
        if (! RateLimiter::attempt($rateLimitKey, 2, fn (): bool => true, 60)) {
            throw new DeferredPaymentWebhook('The AFS status request limit for this checkout has been reached.');
        }

        $response = Http::withToken($this->requiredAccessToken())
            ->acceptJson()
            ->timeout(15)
            ->get($this->endpoint($path), ['entityId' => $this->requiredEntityId()]);

        if ($allowMissing && in_array($response->status(), [400, 404], true)) {
            return null;
        }

        if (! $response->successful()) {
            throw new InvalidPaymentWebhook('AFS payment status could not be verified.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new InvalidPaymentWebhook('AFS returned an unexpected payment status.');
        }

        $entityId = data_get($data, 'authentication.entityId');
        if (is_string($entityId) && $entityId !== '' && ! hash_equals($this->requiredEntityId(), $entityId)) {
            throw new InvalidPaymentWebhook('AFS returned a payment status for an unexpected merchant entity.');
        }

        return $data;
    }

    /**
     * Find the checkout that owns a webhook transaction so all AFS status
     * lookups are throttled against the checkout, including refund events.
     *
     * @param  array<string, mixed>  $payload
     */
    private function checkoutReferenceForWebhook(array $payload): string
    {
        $reference = $payload['merchantTransactionId'] ?? null;
        $payment = is_string($reference)
            ? Payment::where('transaction_reference', $reference)->where('gateway', $this->name())->first()
            : null;

        if (! $payment && is_string($payload['referencedId'] ?? null)) {
            $payment = Payment::where('gateway', $this->name())
                ->where('gateway_response->provider_reference', $payload['referencedId'])
                ->first();
        }

        if (! $payment || ! is_string($payment->gateway_reference) || $payment->gateway_reference === '') {
            throw new InvalidPaymentWebhook('The AFS notification does not match a known checkout.');
        }

        return $payment->gateway_reference;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function assertSupportedBrand(array $status, string $classification): void
    {
        if ($classification !== AfsResultCode::PAID) {
            return;
        }

        $brand = $status['paymentBrand'] ?? null;
        $configuredBrands = preg_split('/[\s,]+/', strtoupper(trim((string) config('payments.afs.brands', ''))), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! is_string($brand) || ! in_array(strtoupper($brand), $configuredBrands, true)) {
            throw new DeferredPaymentWebhook('The AFS payment brand is not configured for this merchant.');
        }
    }

    /** @param array<string, mixed> $status */
    private function providerString(array $status, string $key): ?string
    {
        $value = $status[$key] ?? null;

        return is_string($value) && $value !== '' ? mb_substr($value, 0, 40) : null;
    }

    /** @param array<string, mixed> $status */
    private function transactionTimestamp(array $status): ?string
    {
        $value = $status['timestamp'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validIntegrity(mixed $integrity): bool
    {
        return is_string($integrity)
            && preg_match('/\Asha(?:256|384|512)-[A-Za-z0-9+\/]+={0,2}(?:\s+sha(?:256|384|512)-[A-Za-z0-9+\/]+={0,2})*\z/', $integrity) === 1;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function amount(array $status): string
    {
        $amount = $status['amount'] ?? null;

        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            throw new InvalidPaymentWebhook('The AFS transaction has no amount.');
        }

        try {
            return BhdAmount::normalize((string) $amount);
        } catch (InvalidArgumentException) {
            throw new InvalidPaymentWebhook('The AFS transaction amount is invalid.');
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function eventId(array $status): string
    {
        $paymentId = (string) ($status['id'] ?? '');

        if ($paymentId === '' || strlen($paymentId) > 100) {
            throw new InvalidPaymentWebhook('The AFS transaction has no usable identifier.');
        }

        return mb_substr($paymentId.':'.$this->resultCode($status), 0, 150);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function providerReference(array $status): ?string
    {
        $paymentId = $status['id'] ?? null;

        return is_string($paymentId) && $paymentId !== '' ? $paymentId : null;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function resultCode(array $status): string
    {
        $code = $status['result']['code'] ?? '';

        return is_string($code) ? $code : '';
    }

    /** @param array<string, mixed> $status */
    private function resultDescription(array $status): ?string
    {
        $description = data_get($status, 'result.description');

        return is_string($description) && $description !== '' ? mb_substr($description, 0, 1000) : null;
    }

    /** @param array<string, mixed> $status */
    private function providerEntityId(array $status): ?string
    {
        $entityId = data_get($status, 'authentication.entityId');

        return is_string($entityId) && $entityId !== '' ? mb_substr($entityId, 0, 64) : null;
    }

    /**
     * Preserve non-secret 3DS outcome metadata for reconciliation without storing CAVV/AAV.
     *
     * @param  array<string, mixed>  $status
     * @return array<string, string>
     */
    private function threeDSecureDetails(array $status): array
    {
        $details = [];
        foreach ([
            'version', 'authenticationStatus', 'eci', 'dsTransactionId', 'acsTransactionId',
            'challengeMandatedIndicator', 'authType', 'transactionStatusReason',
        ] as $key) {
            $value = data_get($status, 'threeDSecure.'.$key);
            if (is_string($value) && $value !== '') {
                $details[$key] = mb_substr($value, 0, 100);
            }
        }

        return $details;
    }

    /**
     * @return array<string, string>
     */
    private function checkoutParameters(Payment $payment, string $returnUrl, string $entityId): array
    {
        $name = preg_split('/\s+/', trim((string) $payment->customer_name), 2) ?: [];
        $givenName = mb_substr($name[0] ?: 'Customer', 0, 50);
        $surname = mb_substr($name[1] ?? $givenName, 0, 50);

        $parameters = [
            'entityId' => $entityId,
            'amount' => BhdAmount::gatewayAmount((string) $payment->amount),
            'currency' => (string) $payment->currency,
            'paymentType' => (string) config('payments.default_type', 'DB'),
            'merchantTransactionId' => (string) $payment->transaction_reference,
            'merchantInvoiceId' => (string) $payment->transaction_reference,
            'descriptor' => mb_substr((string) config('payments.afs.descriptor', 'Edu World'), 0, 127),
            'shopperResultUrl' => $returnUrl,
            'customer.givenName' => $givenName,
            'customer.surname' => $surname,
            'customer.language' => 'EN',
            'billing.country' => 'BH',
        ];

        if (config('payments.afs.widget_integrity')) {
            $parameters['integrity'] = 'true';
        }

        if ($payment->email) {
            $parameters['customer.email'] = (string) $payment->email;
        }

        if ($payment->phone) {
            $parameters['customer.phone'] = (string) $payment->phone;
        }

        $testMode = strtoupper((string) config('payments.afs.test_mode', ''));

        // The API rejects testMode on the production host (600.200.701).
        if (in_array($testMode, ['INTERNAL', 'EXTERNAL'], true)
            && parse_url($this->requiredBaseUrl(), PHP_URL_HOST) !== 'eu-prod.oppwa.com') {
            $parameters['testMode'] = $testMode;
        }

        return $parameters;
    }

    private function entityId(): ?string
    {
        $entityId = trim((string) config('payments.afs.entity_id'));

        return preg_match('/\A[a-f0-9]{32}\z/i', $entityId) === 1 ? $entityId : null;
    }

    private function accessToken(): ?string
    {
        $token = trim((string) config('payments.afs.access_token'));

        return $token !== '' ? $token : null;
    }

    private function baseUrl(): ?string
    {
        $url = rtrim(trim((string) config('payments.afs.base_url')), '/').'/';

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! is_string(parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        return $url;
    }

    private function endpoint(string $path): string
    {
        return $this->requiredBaseUrl().ltrim($path, '/');
    }

    private function requiredEntityId(): string
    {
        return $this->entityId() ?? throw new LogicException('The AFS entity ID is not configured.');
    }

    private function requiredAccessToken(): string
    {
        return $this->accessToken() ?? throw new LogicException('The AFS access token is not configured.');
    }

    private function requiredBaseUrl(): string
    {
        return $this->baseUrl() ?? throw new LogicException('The AFS base URL is not configured.');
    }
}

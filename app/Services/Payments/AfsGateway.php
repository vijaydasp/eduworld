<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        return $this->entityId() !== null
            && $this->accessToken() !== null
            && $baseUrl !== null
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

        if (! is_string($checkoutId)
            || preg_match(self::CHECKOUT_ID_PATTERN, $checkoutId) !== 1
            || ! AfsResultCode::isCheckoutCreated(is_string($resultCode) ? $resultCode : null)) {
            throw new RuntimeException('AFS did not return a usable checkout session.');
        }

        return new GatewayCheckout($baseUrl, $checkoutId);
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

        return new GatewayCheckout($this->requiredBaseUrl(), $reference);
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
        return $this->eventFromStatus($this->paymentStatus($paymentId));
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

            $status = $this->status(ltrim($resourcePath, '/'), allowMissing: true);

            return $status === null ? null : $this->checkoutEvent($status, $payment);
        }

        $paymentId = $request->query('id');

        if (is_string($paymentId) && preg_match(self::PAYMENT_ID_PATTERN, $paymentId) === 1) {
            $status = $this->paymentStatus($paymentId);

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

        if ($classification === AfsResultCode::PENDING) {
            throw new DeferredPaymentWebhook('The AFS transaction is not in a final settlement state.');
        }

        $paymentType = strtoupper((string) ($status['paymentType'] ?? ''));

        if (in_array($paymentType, ['RF', 'RV'], true)) {
            return $this->refundEvent($status, $classification);
        }

        if (! in_array($paymentType, ['DB', 'PA'], true)) {
            throw new DeferredPaymentWebhook('The AFS payment type is not supported for settlement.');
        }

        $reference = (string) ($status['merchantTransactionId'] ?? '');
        $payment = Payment::where('transaction_reference', $reference)->first();

        if (! $payment
            || ! hash_equals($this->name(), (string) $payment->gateway)
            || ! $payment->gateway_reference) {
            throw new InvalidPaymentWebhook('The AFS transaction does not match a payment session.');
        }

        return $this->settlementEvent($status, $payment, $classification);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function checkoutEvent(array $status, Payment $payment): ?VerifiedPaymentEvent
    {
        $classification = AfsResultCode::classify($this->resultCode($status));

        if ($classification === AfsResultCode::PENDING) {
            return null;
        }

        $paymentType = strtoupper((string) ($status['paymentType'] ?? ''));

        if (! in_array($paymentType, ['DB', 'PA'], true)) {
            return null;
        }

        $reference = $status['merchantTransactionId'] ?? null;

        if ($reference !== null && ! hash_equals((string) $payment->transaction_reference, (string) $reference)) {
            return null;
        }

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
    private function paymentStatus(string $paymentId): array
    {
        $status = $this->status('v1/payments/'.rawurlencode($paymentId), allowMissing: false);

        if ($status === null || ($status['id'] ?? null) !== $paymentId) {
            throw new InvalidPaymentWebhook('AFS returned an unexpected payment status.');
        }

        return $status;
    }

    /** @return array<string, mixed>|null */
    private function status(string $path, bool $allowMissing): ?array
    {
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

        return $data;
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
            'paymentType' => 'DB',
            'merchantTransactionId' => (string) $payment->transaction_reference,
            'merchantInvoiceId' => (string) $payment->transaction_reference,
            'descriptor' => mb_substr((string) config('payments.afs.descriptor', 'Edu World'), 0, 127),
            'shopperResultUrl' => $returnUrl,
            'customer.givenName' => $givenName,
            'customer.surname' => $surname,
            'customer.language' => 'EN',
            'billing.country' => 'BH',
        ];

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

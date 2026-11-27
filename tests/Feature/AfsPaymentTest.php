<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AfsPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITY_ID = '8ac7a4c797662439019773da2ea107eb';

    private const CHECKOUT_ID = '8ac7a4c797662439019773da2ea107ec';

    private const PAYMENT_ID = '8ac7a4c797662439019773da2ea107ed';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();

        config([
            'payments.driver' => 'afs',
            'payments.checkout_hosts' => ['eu-test.oppwa.com'],
            'payments.afs' => [
                'base_url' => 'https://eu-test.oppwa.com/',
                'entity_id' => self::ENTITY_ID,
                'access_token' => 'test-access-token',
                'webhook_secret' => str_repeat('ab', 32),
                'brands' => 'VISA MASTER',
                'descriptor' => 'Edu World',
                'test_mode' => 'INTERNAL',
            ],
            'eduworld.turnstile_site_key' => null,
            'eduworld.turnstile_secret_key' => null,
        ]);
    }

    public function test_pay_now_creates_an_afs_checkout_for_the_entered_amount_and_shows_card_form(): void
    {
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts' => Http::response([
                'id' => self::CHECKOUT_ID,
                'result' => ['code' => '000.200.100', 'description' => 'successfully created checkout'],
            ]),
        ]);

        $response = $this->post(route('pay.store'), [
            'customer_name' => 'Test Applicant',
            'phone' => '+97333640001',
            'email' => 'payer@example.test',
            'entered_reference' => 'EW-2026-00001',
            'service' => 'study_abroad',
            'amount' => '12.340',
            'terms' => '1',
            'application_id' => 999,
            'status' => 'paid',
            'amount_received' => '12.340',
        ]);

        $response->assertRedirect();
        $payment = Payment::query()->firstOrFail();
        $this->assertSame('afs', $payment->gateway);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('0.000', $payment->amount_received);
        $this->assertSame('12.340', $payment->amount);
        $this->assertNull($payment->application_id);
        $this->assertSame(self::CHECKOUT_ID, $payment->gateway_reference);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://eu-test.oppwa.com/v1/checkouts'
            && $request->method() === 'POST'
            && $request['amount'] === '12.34'
            && $request['currency'] === 'BHD'
            && $request['paymentType'] === 'DB');

        $response->assertRedirectContains('/pay/checkout/'.$payment->id);
        $checkoutUrl = $response->headers->get('Location');
        $this->get($checkoutUrl)
            ->assertOk()
            ->assertSee('https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId='.self::CHECKOUT_ID, false)
            ->assertSee('crossorigin="anonymous"', false)
            ->assertSee('data-brands="VISA MASTER"', false)
            ->assertSee('copyandpay-loading', false)
            ->assertSee('onError: function()', false)
            ->assertSee('onAfterSubmit: function()', false)
            ->assertSee($payment->transaction_reference);
    }

    public function test_public_payment_remains_pending_when_afs_cannot_prepare_checkout(): void
    {
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts' => Http::response([
                'result' => ['code' => '800.100.100', 'description' => 'checkout unavailable'],
            ], 503),
        ]);

        $this->from(route('pay'))
            ->post(route('pay.store'), [
                'customer_name' => 'Test Applicant',
                'phone' => '+97333640001',
                'email' => 'payer@example.test',
                'service' => 'study_abroad',
                'amount' => '12.340',
                'terms' => '1',
            ])
            ->assertRedirect(route('pay'))
            ->assertSessionHasErrors('payment');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('0.000', $payment->amount_received);
        $this->assertNull($payment->gateway_reference);
    }

    public function test_checkout_session_is_created_server_to_server_and_rendered_on_our_signed_page(): void
    {
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts' => Http::response([
                'id' => self::CHECKOUT_ID,
                'result' => ['code' => '000.200.100', 'description' => 'successfully created checkout'],
            ]),
        ]);

        $payments = app(PaymentManager::class);
        $payment = $payments->createIntent([
            'application_id' => null,
            'customer_name' => 'Test Applicant',
            'phone' => '+97333640001',
            'email' => 'payer@example.test',
            'entered_reference' => null,
            'service' => 'other',
            'amount' => '12.340',
            'remarks' => null,
        ]);

        $checkout = $payments->checkout($payment);
        $this->assertNotNull($checkout);
        $payment->refresh();

        $this->assertSame('afs', $payment->gateway);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(self::CHECKOUT_ID, $payment->gateway_reference);

        Http::assertSent(function (Request $request) use ($payment): bool {
            return $request->url() === 'https://eu-test.oppwa.com/v1/checkouts'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['entityId'] === self::ENTITY_ID
                && $request['amount'] === '12.34'
                && $request['currency'] === 'BHD'
                && $request['paymentType'] === 'DB'
                && $request['merchantTransactionId'] === $payment->transaction_reference
                && $request['shopperResultUrl'] === route('pay.return', ['reference' => $payment->transaction_reference])
                && $request['testMode'] === 'INTERNAL';
        });

        $location = URL::temporarySignedRoute('pay.checkout', now()->addMinutes(35), ['payment' => $payment->id]);

        $this->get($location)
            ->assertOk()
            ->assertSee('https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId='.self::CHECKOUT_ID, false)
            ->assertSee('data-brands="VISA MASTER"', false)
            ->assertHeader('Content-Security-Policy');
    }

    public function test_webhook_settles_only_from_the_provider_status_and_ignores_body_amounts(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->successfulStatus($payment));

        $this->sendWebhook([
            'id' => self::PAYMENT_ID,
            'merchantTransactionId' => $payment->transaction_reference,
            'amount' => '0.001',
            'currency' => 'USD',
            'result' => ['code' => '800.100.153', 'description' => 'declined'],
            'authentication' => ['entityId' => self::ENTITY_ID],
        ])->assertOk()->assertJson(['received' => true]);

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('12.340', $payment->amount_received);
        $this->assertSame(self::PAYMENT_ID, $payment->gateway_response['provider_reference']);
        $this->assertSame('DB', $payment->provider_payment_type);
        $this->assertSame('MASTER', $payment->provider_payment_brand);
        $this->assertSame('000.000.000', $payment->provider_result_code);
        $this->assertSame('Provider result', $payment->gateway_response['provider_result_description']);
        $this->assertSame('2.2.0', $payment->gateway_response['three_d_secure']['version']);
        $this->assertSame('Y', $payment->gateway_response['three_d_secure']['authenticationStatus']);
        $this->assertArrayNotHasKey('verificationId', $payment->gateway_response['three_d_secure']);
        $this->assertNotNull($payment->provider_transaction_at);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseHas('payment_events', ['event_id' => self::PAYMENT_ID.':000.000.000', 'status' => 'paid']);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
        Http::assertSentCount(1);
    }

    public function test_webhook_maps_declines_without_settling(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->providerStatus($payment, '800.100.153'));

        $this->sendWebhook($this->notification($payment))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('0.000', $payment->fresh()->amount_received);
        $this->assertDatabaseCount('payment_events', 1);
        Notification::assertNothingSent();
    }

    public function test_webhook_acknowledges_pending_codes_without_writing_an_event(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->providerStatus($payment, '000.200.000'));

        $this->sendWebhook($this->notification($payment))
            ->assertStatus(202)
            ->assertJson(['received' => true, 'status' => 'pending']);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_technical_and_unknown_result_codes_remain_pending(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->providerStatus($payment, '900.100.100'));

        $this->sendWebhook($this->notification($payment))
            ->assertStatus(202)
            ->assertJson(['received' => true, 'status' => 'pending']);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_result_codes_requiring_manual_review_are_not_settled_automatically(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->providerStatus($payment, '000.400.000'));

        $this->sendWebhook($this->notification($payment))
            ->assertStatus(202)
            ->assertJson(['received' => true, 'status' => 'pending']);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_payment_status_requests_are_limited_to_two_per_checkout_per_minute(): void
    {
        RateLimiter::clear('afs-status:'.hash('sha256', self::CHECKOUT_ID));
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts/*' => Http::response([
                'id' => self::PAYMENT_ID,
                'paymentType' => 'DB',
                'merchantTransactionId' => 'EWP-2026-0000123',
                'result' => ['code' => '000.200.000'],
                'authentication' => ['entityId' => self::ENTITY_ID],
            ]),
        ]);
        $payment = $this->pendingPayment();

        foreach (range(1, 3) as $_) {
            $this->get(route('pay.return', [
                'reference' => $payment->transaction_reference,
                'resourcePath' => '/v1/checkouts/'.self::CHECKOUT_ID.'/payment',
            ]))->assertRedirect();
        }

        Http::assertSentCount(2);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_widget_uses_checkout_integrity_when_configured(): void
    {
        config(['payments.afs.widget_integrity' => true]);
        $integrity = 'sha384-YWZjaGRlZmdoaWprbG1ub3BxcnN0dXZ3eHl6';
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts' => Http::response([
                'id' => self::CHECKOUT_ID,
                'integrity' => $integrity,
                'result' => ['code' => '000.200.100'],
            ]),
        ]);

        $payment = app(PaymentManager::class)->createIntent([
            'customer_name' => 'Test Applicant',
            'phone' => '+97333640001',
            'email' => 'payer@example.test',
            'service' => 'other',
            'amount' => '12.340',
        ]);
        app(PaymentManager::class)->checkout($payment);
        $payment->refresh();
        $url = URL::temporarySignedRoute('pay.checkout', now()->addMinutes(35), ['payment' => $payment->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('integrity="'.$integrity.'"', false)
            ->assertSee('crossorigin="anonymous"', false);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://eu-test.oppwa.com/v1/checkouts'
            && $request['integrity'] === 'true');
        Notification::assertNothingSent();
    }

    public function test_webhook_rejects_tampered_encryption_and_unknown_merchant_entities(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->successfulStatus($payment));

        $this->sendWebhook($this->notification($payment), tamperTag: true)->assertForbidden();
        $this->sendWebhook(array_replace_recursive($this->notification($payment), [
            'authentication' => ['entityId' => str_repeat('c', 32)],
        ]))->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
        Http::assertNothingSent();
    }

    public function test_verified_provider_amount_must_match_the_stored_amount(): void
    {
        $payment = $this->pendingPayment();
        $status = $this->successfulStatus($payment);
        $status['amount'] = '99.000';
        $this->fakePaymentStatus($status);

        $this->sendWebhook($this->notification($payment))->assertUnprocessable();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
        Notification::assertNothingSent();
    }

    public function test_duplicate_webhook_delivery_is_idempotent(): void
    {
        $payment = $this->pendingPayment();
        $this->fakePaymentStatus($this->successfulStatus($payment));

        $this->sendWebhook($this->notification($payment))->assertOk();
        $this->sendWebhook($this->notification($payment))->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 1);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
    }

    public function test_shopper_return_verifies_the_checkout_server_to_server(): void
    {
        $payment = $this->pendingPayment();
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts/*' => Http::response($this->successfulStatus($payment)),
        ]);

        $response = $this->get(route('pay.return', [
            'reference' => $payment->transaction_reference,
            'resourcePath' => '/v1/checkouts/'.self::CHECKOUT_ID.'/payment',
            'id' => self::PAYMENT_ID,
        ]));

        $response->assertRedirect();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('12.340', $payment->fresh()->amount_received);
        Http::assertSent(fn (Request $request): bool => $request['entityId'] === self::ENTITY_ID);

        $this->get((string) $response->headers->get('Location'))->assertOk()->assertSee('Payment successful');
    }

    public function test_checkout_status_can_be_verified_when_the_api_does_not_echo_the_entity_id(): void
    {
        $payment = $this->pendingPayment();
        $status = $this->successfulStatus($payment);
        unset($status['authentication']);
        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts/*' => Http::response($status),
        ]);

        $this->get(route('pay.return', [
            'reference' => $payment->transaction_reference,
            'resourcePath' => '/v1/checkouts/'.self::CHECKOUT_ID.'/payment',
        ]))->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        Http::assertSent(fn (Request $request): bool => $request['entityId'] === self::ENTITY_ID);
    }

    public function test_admin_payment_details_show_the_afs_transaction_id_separately_from_the_checkout_id(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['gateway_response' => ['provider_reference' => self::PAYMENT_ID]]);
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));

        $this->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee('AFS transaction ID')
            ->assertSee(self::PAYMENT_ID)
            ->assertSee('Provider checkout / offline reference');
    }

    public function test_shopper_return_cannot_reconcile_another_checkout(): void
    {
        $payment = $this->pendingPayment();
        Http::fake();

        $this->get(route('pay.return', [
            'reference' => $payment->transaction_reference,
            'resourcePath' => '/v1/checkouts/'.str_repeat('d', 32).'/payment',
        ]))->assertRedirect();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_frontend_success_parameters_do_not_mark_a_payment_as_paid(): void
    {
        $payment = $this->pendingPayment();
        Http::fake();

        $this->get(route('pay.return', [
            'reference' => $payment->transaction_reference,
            'success' => 'true',
            'payment_status' => 'success',
            'paid' => 'true',
        ]))->assertRedirect();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_environment_and_transaction_configuration_must_match_before_checkout_is_enabled(): void
    {
        $gateway = app(PaymentManager::class)->gateway();
        $this->assertTrue($gateway->available());

        config(['payments.environment' => 'live']);
        $this->assertFalse($gateway->available());

        config([
            'payments.afs.base_url' => 'https://eu-prod.oppwa.com/',
            'payments.checkout_hosts' => ['eu-prod.oppwa.com'],
        ]);
        $this->assertTrue($gateway->available());

        config(['payments.default_type' => 'PA']);
        $this->assertFalse($gateway->available());
        config(['payments.default_type' => 'DB', 'payments.currency' => 'USD']);
        $this->assertFalse($gateway->available());
    }

    public function test_a_full_refund_notification_is_matched_to_the_settled_payment(): void
    {
        $originalPaymentId = '8ac7a4c797662439019773da2ea107e1';
        $refundPaymentId = '8ac7a4c797662439019773da2ea107e2';

        $payment = Payment::factory()->create([
            'transaction_reference' => 'EWP-2026-0000124',
            'gateway' => 'afs',
            'gateway_reference' => self::CHECKOUT_ID,
            'status' => 'paid',
            'amount' => '12.340',
            'amount_received' => '12.340',
            'paid_at' => now(),
            'gateway_response' => ['provider_reference' => $originalPaymentId],
        ]);

        Http::fake([
            'https://eu-test.oppwa.com/v1/payments/*' => Http::response([
                'id' => $refundPaymentId,
                'referencedId' => $originalPaymentId,
                'paymentType' => 'RF',
                'amount' => '12.340',
                'currency' => 'BHD',
                'result' => ['code' => '000.000.000', 'description' => 'Transaction succeeded'],
                'authentication' => ['entityId' => self::ENTITY_ID],
            ]),
        ]);

        $this->sendWebhook([
            'id' => $refundPaymentId,
            'referencedId' => $originalPaymentId,
            'merchantTransactionId' => 'REFUND-REFERENCE',
            'result' => ['code' => '000.000.000'],
            'authentication' => ['entityId' => self::ENTITY_ID],
        ])->assertOk();

        $payment->refresh();

        $this->assertSame('refunded', $payment->status);
        $this->assertSame('0.000', $payment->amount_received);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame($originalPaymentId, $payment->gateway_response['provider_reference']);
    }

    private function pendingPayment(): Payment
    {
        return Payment::factory()->create([
            'transaction_reference' => 'EWP-2026-0000123',
            'gateway' => 'afs',
            'gateway_reference' => self::CHECKOUT_ID,
            'status' => 'pending',
            'amount' => '12.340',
            'amount_received' => '0.000',
            'currency' => 'BHD',
            'channel' => 'gateway',
        ]);
    }

    /** @return array<string, mixed> */
    private function successfulStatus(Payment $payment): array
    {
        return $this->providerStatus($payment, '000.000.000');
    }

    /** @return array<string, mixed> */
    private function providerStatus(Payment $payment, string $code): array
    {
        return [
            'id' => self::PAYMENT_ID,
            'paymentType' => 'DB',
            'paymentBrand' => 'MASTER',
            'amount' => '12.340',
            'currency' => 'BHD',
            'timestamp' => now()->toIso8601String(),
            'merchantTransactionId' => $payment->transaction_reference,
            'result' => ['code' => $code, 'description' => 'Provider result'],
            'threeDSecure' => [
                'version' => '2.2.0',
                'authenticationStatus' => 'Y',
                'eci' => '05',
                'verificationId' => 'must-not-be-stored',
            ],
            'authentication' => ['entityId' => self::ENTITY_ID],
        ];
    }

    /** @return array<string, mixed> */
    private function notification(Payment $payment): array
    {
        return [
            'id' => self::PAYMENT_ID,
            'merchantTransactionId' => $payment->transaction_reference,
            'result' => ['code' => '000.000.000'],
            'authentication' => ['entityId' => self::ENTITY_ID],
        ];
    }

    /** @param  array<string, mixed>  $status */
    private function fakePaymentStatus(array $status): void
    {
        Http::fake(['https://eu-test.oppwa.com/v1/payments/*' => Http::response($status)]);
    }

    /** @param  array<string, mixed>  $payload */
    private function sendWebhook(array $payload, bool $tamperTag = false): TestResponse
    {
        $key = hex2bin(str_repeat('ab', 32));
        $iv = random_bytes(12);
        $tag = '';
        $plaintext = json_encode(['type' => 'PAYMENT', 'payload' => $payload], JSON_THROW_ON_ERROR);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        $tag = $tamperTag ? str_repeat('00', 16) : $tag;
        $body = json_encode(['encryptedBody' => bin2hex($ciphertext)], JSON_THROW_ON_ERROR);

        return $this->call('POST', route('pay.webhook', 'afs'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INITIALIZATION_VECTOR' => bin2hex($iv),
            'HTTP_X_AUTHENTICATION_TAG' => bin2hex($tag),
        ], $body);
    }

    /** @return array<string, mixed> */
    private function paymentData(): array
    {
        return ['customer_name' => 'Test Applicant', 'phone' => '+97333640001', 'email' => 'payer@example.test', 'service' => 'other', 'amount' => '12.340', 'terms' => '1'];
    }
}

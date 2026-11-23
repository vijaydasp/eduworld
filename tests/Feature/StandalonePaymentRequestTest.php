<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Notifications\PaymentRequestReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StandalonePaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITY_ID = '8ac7a4c797662439019773da2ea107eb';

    private const CHECKOUT_ID = '8ac7a4c797662439019773da2ea107ec';

    private const CHECKOUT_ID_2 = '8ac7a4c797662439019773da2ea107ee';

    private const PAYMENT_ID = '8ac7a4c797662439019773da2ea107ed';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        config(['payments.driver' => 'disabled']);
    }

    public function test_authorized_payment_admin_can_create_a_standalone_request_and_email_the_customer(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.store'), $this->createData())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $paymentRequest = PaymentRequest::sole();

        $this->assertSame(PaymentRequest::TYPE_STANDALONE, $paymentRequest->type);
        $this->assertNull($paymentRequest->application_id);
        $this->assertSame('Standalone Customer', $paymentRequest->customer_name);
        $this->assertSame('customer@example.test', $paymentRequest->customer_email);
        $this->assertSame('+973 39000000', $paymentRequest->customer_phone);
        $this->assertSame('25.000', $paymentRequest->amount);
        $this->assertSame('BHD', $paymentRequest->currency);
        $this->assertSame('sent', $paymentRequest->status);
        $this->assertSame($admin->id, $paymentRequest->created_by);
        $this->assertNotNull($paymentRequest->public_id);
        $this->assertNotNull($paymentRequest->sent_at);
        $this->assertTrue($paymentRequest->expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.created', 'subject_id' => $paymentRequest->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.sent', 'subject_id' => $paymentRequest->id]);

        Notification::assertSentOnDemand(PaymentRequestReady::class, function (PaymentRequestReady $notification, array $channels, object $notifiable): bool {
            $mail = $notification->toMail($notifiable);
            $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return $notifiable->routes['mail'] === 'customer@example.test'
                && str_contains($mail->subject, 'Consultation fee')
                && str_contains($body, 'BHD 25.000')
                && str_contains($body, 'Consultation fee')
                && ! str_contains($body, 'Application reference')
                && str_contains($body, 'AFS')
                && str_contains((string) $mail->actionUrl, '/pay/request/'.$notification->paymentRequest->public_id.'/checkout');
        });
    }

    public function test_guests_and_content_managers_cannot_create_standalone_requests(): void
    {
        $this->post(route('admin.payments.requests.store'), $this->createData())
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]))
            ->post(route('admin.payments.requests.store'), $this->createData())
            ->assertForbidden();

        $this->assertDatabaseCount('payment_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_standalone_creation_rejects_invalid_currency_application_injection_and_bad_amounts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.store'), array_replace($this->createData(), ['currency' => 'USD']))
            ->assertSessionHasErrors('currency');

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.store'), $this->createData() + ['application_id' => 99])
            ->assertSessionHasErrors('application_id');

        foreach (['1.2345', '0', '-1', '1e3'] as $amount) {
            $this->actingAs($admin)
                ->post(route('admin.payments.requests.store'), array_replace($this->createData(), ['amount' => $amount]))
                ->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_admin_standalone_pages_render_without_application_fields_and_guard_request_type(): void
    {
        $standalone = PaymentRequest::factory()->standalone()->create();
        $applicationRequest = PaymentRequest::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Create payment request')
            ->assertSee('Standalone requests')
            ->assertSee('Legacy / unlinked payments');

        $this->actingAs($this->admin())
            ->get(route('admin.payments.requests.index'))
            ->assertOk()
            ->assertSee($standalone->customer_name)
            ->assertSee($standalone->customer_email);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.requests.create'))
            ->assertOk()
            ->assertSee('Create &amp; Send Payment Request', false)
            ->assertDontSee('name="application_id"', false);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.requests.show', $standalone))
            ->assertOk()
            ->assertSee($standalone->public_id)
            ->assertSee($standalone->description);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.requests.show', $applicationRequest))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->post(route('admin.payments.requests.cancel', $applicationRequest))
            ->assertNotFound();
    }

    public function test_signed_standalone_page_shows_only_customer_and_payment_details(): void
    {
        $paymentRequest = PaymentRequest::factory()->standalone()->create([
            'customer_name' => 'Standalone Customer',
            'customer_email' => 'customer@example.test',
            'description' => 'Consultation fee',
            'amount' => '25.000',
        ]);

        $this->get($this->showUrl($paymentRequest))
            ->assertOk()
            ->assertSee('Standalone Customer')
            ->assertSee('Consultation fee')
            ->assertSee('BHD 25.000')
            ->assertSee('Pay securely via AFS')
            ->assertDontSee('Application reference')
            ->assertDontSee('Service / course')
            ->assertDontSee('name="amount"', false)
            ->assertDontSee('name="entered_reference"', false)
            ->assertDontSee('name="application_id"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_standalone_signed_links_reject_tampering_and_expiry(): void
    {
        $paymentRequest = PaymentRequest::factory()->standalone()->create();

        $this->get($this->showUrl($paymentRequest).'&amount=1.000')->assertForbidden();

        $expiredRequest = PaymentRequest::factory()->standalone()->create([
            'expires_at' => now()->subHour(),
        ]);

        $this->get(URL::temporarySignedRoute('pay.request.show', now()->addMinutes(5), ['paymentRequest' => $expiredRequest->public_id]))
            ->assertStatus(410);

        $this->assertSame('expired', $expiredRequest->fresh()->status);

        $this->get(URL::temporarySignedRoute('pay.request.show', now()->subMinute(), ['paymentRequest' => $paymentRequest->public_id]))
            ->assertForbidden();
    }

    public function test_cancelled_standalone_request_cannot_start_a_checkout(): void
    {
        $paymentRequest = PaymentRequest::factory()->standalone()->cancelled()->create();

        $this->get($this->showUrl($paymentRequest))->assertStatus(410);
        $this->post($this->payUrl($paymentRequest))->assertStatus(410);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_standalone_checkout_creates_an_unlinked_bhd_payment_and_settles_once(): void
    {
        $this->configureAfs();
        $this->fakeAfs();

        $paymentRequest = PaymentRequest::factory()->standalone()->create([
            'customer_name' => 'Standalone Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '+973 39000000',
            'description' => 'Consultation fee',
            'amount' => '25.000',
        ]);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();

        $payment = Payment::sole();

        $this->assertNull($payment->application_id);
        $this->assertSame('other', $payment->service);
        $this->assertSame('25.000', $payment->amount);
        $this->assertSame('BHD', $payment->currency);
        $this->assertSame('Standalone Customer', $payment->customer_name);
        $this->assertSame('customer@example.test', $payment->email);
        $this->assertSame('+973 39000000', $payment->phone);
        $this->assertSame($payment->id, $paymentRequest->fresh()->payment_id);
        $this->assertDatabaseCount('applications', 0);

        $this->sendWebhook($this->notification($payment))->assertOk();

        $payment->refresh();
        $paymentRequest->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $paymentRequest->status);
        $this->assertNotNull($paymentRequest->paid_at);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
        Notification::assertSentOnDemand(PaymentConfirmed::class, function (PaymentConfirmed $notification, array $channels, object $notifiable): bool {
            $body = implode(' ', $notification->toMail($notifiable)->introLines);

            return $notifiable->routes['mail'] === 'customer@example.test'
                && str_contains($body, 'Consultation fee')
                && ! str_contains($body, 'Application reference');
        });

        $this->sendWebhook($this->notification($payment))->assertOk();

        $this->assertDatabaseCount('payment_events', 1);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
        $this->assertSame('paid', $paymentRequest->fresh()->status);
    }

    public function test_standalone_payment_is_listed_in_all_admin_payments(): void
    {
        $this->configureAfs();
        $this->fakeAfs();

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $payment = Payment::sole();

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($payment->transaction_reference)
            ->assertSee('Standalone request');
    }

    public function test_signed_email_checkout_link_opens_the_afs_widget_directly(): void
    {
        $this->configureAfs();
        $this->fakeAfs();

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $url = URL::temporarySignedRoute(
            'pay.request.checkout',
            $paymentRequest->expires_at,
            ['paymentRequest' => $paymentRequest->public_id],
        );

        $response = $this->get($url)->assertRedirect();

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId='.self::CHECKOUT_ID, false)
            ->assertSee('Complete your payment');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(self::CHECKOUT_ID, Payment::sole()->gateway_reference);

        $this->get($url.'&amount=1.000')->assertForbidden();
    }

    public function test_signed_email_checkout_link_shows_a_retry_message_when_the_gateway_is_unavailable(): void
    {
        config(['payments.driver' => 'disabled']);

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $url = URL::temporarySignedRoute(
            'pay.request.checkout',
            $paymentRequest->expires_at,
            ['paymentRequest' => $paymentRequest->public_id],
        );

        $response = $this->get($url)->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/pay/request/'.$paymentRequest->public_id, $location);
        $this->assertStringNotContainsString('/pay/receipt/', $location);

        $this->get($location)
            ->assertOk()
            ->assertSee('We could not start the secure AFS payment', false);
    }

    public function test_pending_standalone_request_is_visible_in_the_payments_workspace(): void
    {
        PaymentRequest::factory()->standalone()->create([
            'customer_name' => 'Awaiting Customer',
            'customer_email' => 'awaiting@example.test',
            'description' => 'Awaiting fee',
            'amount' => '15.000',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Payment requests awaiting payment')
            ->assertSee('Awaiting Customer')
            ->assertSee('BHD 15.000')
            ->assertSee('Standalone request');
    }

    public function test_payment_with_a_matching_entered_reference_is_not_labelled_unlinked(): void
    {
        $application = Application::factory()->create(['reference_number' => 'EW-2026-00042']);
        $payment = Payment::factory()->create([
            'application_id' => null,
            'entered_reference' => 'EW-2026-00042',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($payment->transaction_reference)
            ->assertSee('Application EW-2026-00042')
            ->assertSee('from entered reference')
            ->assertDontSee('<span>Legacy / unlinked</span>', false);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee('Application-linked payment (entered reference)')
            ->assertSee('EW-2026-00042')
            ->assertDontSee('Legacy / unlinked payment');

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['type' => 'application']))
            ->assertOk()
            ->assertSee($payment->transaction_reference);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['type' => 'legacy']))
            ->assertOk()
            ->assertDontSee($payment->transaction_reference);
    }

    public function test_failed_standalone_webhook_does_not_mark_the_request_paid(): void
    {
        $this->configureAfs();
        $this->fakeAfs('800.100.153');

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $payment = Payment::sole();

        $this->sendWebhook($this->notification($payment))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('sent', $paymentRequest->fresh()->status);
        $this->assertNull($paymentRequest->fresh()->paid_at);
        Notification::assertNothingSent();
    }

    public function test_repeated_pay_clicks_reuse_the_same_afs_checkout_session(): void
    {
        $this->configureAfs();
        $this->fakeAfs();

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $this->post($this->payUrl($paymentRequest))->assertRedirect();

        $payment = Payment::sole();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(self::CHECKOUT_ID, $payment->gateway_reference);
        $this->assertNotNull(data_get($payment->gateway_response, 'checkout_started_at'));
        Http::assertSentCount(1);
    }

    public function test_expired_checkout_session_is_reinitialised_and_previous_reference_is_retained(): void
    {
        $this->configureAfs();

        $checkoutCalls = 0;
        Http::fake(function (Request $request) use (&$checkoutCalls) {
            if (str_contains($request->url(), '/v1/checkouts')) {
                $checkoutCalls++;

                return Http::response([
                    'id' => $checkoutCalls === 1 ? self::CHECKOUT_ID : self::CHECKOUT_ID_2,
                    'result' => ['code' => '000.200.100'],
                ]);
            }

            return Http::response([]);
        });

        $paymentRequest = PaymentRequest::factory()->standalone()->create(['amount' => '25.000']);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();

        $payment = Payment::sole();
        $payment->update([
            'gateway_response' => array_merge($payment->gateway_response ?? [], [
                'checkout_started_at' => now()->subHour()->toIso8601String(),
            ]),
        ]);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();

        $payment->refresh();

        $this->assertSame(self::CHECKOUT_ID_2, $payment->gateway_reference);
        $this->assertContains(self::CHECKOUT_ID, $payment->gateway_response['previous_checkout_references']);
        $this->assertSame(2, $checkoutCalls);
    }

    public function test_admin_can_resend_and_cancel_a_standalone_request_but_not_an_expired_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.requests.store'), $this->createData());
        $paymentRequest = PaymentRequest::sole();

        $this->travel(5)->minutes();
        $this->actingAs($admin)
            ->post(route('admin.payments.requests.resend', $paymentRequest))
            ->assertSessionHas('success');

        $this->assertTrue($paymentRequest->fresh()->sent_at->greaterThan($paymentRequest->created_at));

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.cancel', $paymentRequest))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $paymentRequest->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.cancelled', 'subject_id' => $paymentRequest->id]);

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.resend', $paymentRequest))
            ->assertSessionHas('error');

        $expired = PaymentRequest::factory()->standalone()->create([
            'status' => 'sent',
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.requests.resend', $expired))
            ->assertSessionHas('error');

        $this->assertSame('expired', $expired->fresh()->status);
    }

    public function test_admin_payment_filters_distinguish_application_standalone_and_legacy_payments(): void
    {
        $applicationPayment = Payment::factory()->create(['application_id' => Application::factory()]);
        $standaloneRequest = PaymentRequest::factory()->standalone()->create();
        $standalonePayment = Payment::factory()->create(['application_id' => null]);
        $standaloneRequest->update(['payment_id' => $standalonePayment->id]);
        $legacyPayment = Payment::factory()->create(['application_id' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['type' => 'application']))
            ->assertOk()
            ->assertSee($applicationPayment->transaction_reference)
            ->assertDontSee($standalonePayment->transaction_reference)
            ->assertDontSee($legacyPayment->transaction_reference);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['type' => 'standalone']))
            ->assertOk()
            ->assertSee($standalonePayment->transaction_reference)
            ->assertDontSee($applicationPayment->transaction_reference)
            ->assertDontSee($legacyPayment->transaction_reference);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['type' => 'legacy']))
            ->assertOk()
            ->assertSee($legacyPayment->transaction_reference)
            ->assertDontSee($applicationPayment->transaction_reference)
            ->assertDontSee($standalonePayment->transaction_reference);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.show', $standalonePayment))
            ->assertOk()
            ->assertSee('Standalone request payment')
            ->assertSee($standaloneRequest->public_id)
            ->assertSee($standaloneRequest->description);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** @return array<string, string> */
    private function createData(): array
    {
        return [
            'customer_name' => 'Standalone Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '+973 39000000',
            'amount' => '25.000',
            'currency' => 'BHD',
            'description' => 'Consultation fee',
            'validity_days' => '7',
            'confirmed' => '1',
        ];
    }

    private function showUrl(PaymentRequest $paymentRequest, ?Carbon $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'pay.request.show',
            $expiresAt ?? $paymentRequest->expires_at,
            ['paymentRequest' => $paymentRequest->public_id],
        );
    }

    private function payUrl(PaymentRequest $paymentRequest): string
    {
        return URL::temporarySignedRoute(
            'pay.request.pay',
            $paymentRequest->expires_at,
            ['paymentRequest' => $paymentRequest->public_id],
        );
    }

    private function configureAfs(): void
    {
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
        ]);
    }

    private function fakeAfs(string $resultCode = '000.000.000'): void
    {
        Http::fake(function (Request $request) use ($resultCode) {
            if (str_contains($request->url(), '/v1/checkouts')) {
                return Http::response(['id' => self::CHECKOUT_ID, 'result' => ['code' => '000.200.100']]);
            }

            $payment = Payment::query()->latest('id')->firstOrFail();

            return Http::response([
                'id' => self::PAYMENT_ID,
                'paymentType' => 'DB',
                'paymentBrand' => 'MASTER',
                'amount' => '25.000',
                'currency' => 'BHD',
                'merchantTransactionId' => $payment->transaction_reference,
                'result' => ['code' => $resultCode],
                'authentication' => ['entityId' => self::ENTITY_ID],
            ]);
        });
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

    /** @param  array<string, mixed>  $payload */
    private function sendWebhook(array $payload): TestResponse
    {
        $key = hex2bin(str_repeat('ab', 32));
        $iv = random_bytes(12);
        $tag = '';
        $plaintext = json_encode(['type' => 'PAYMENT', 'payload' => $payload], JSON_THROW_ON_ERROR);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        $body = json_encode(['encryptedBody' => bin2hex($ciphertext)], JSON_THROW_ON_ERROR);

        return $this->call('POST', route('pay.webhook', 'afs'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INITIALIZATION_VECTOR' => bin2hex($iv),
            'HTTP_X_AUTHENTICATION_TAG' => bin2hex($tag),
        ], $body);
    }
}

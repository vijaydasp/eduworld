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
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private const ENTITY_ID = '8ac7a4c797662439019773da2ea107eb';

    private const CHECKOUT_ID = '8ac7a4c797662439019773da2ea107ec';

    private const PAYMENT_ID = '8ac7a4c797662439019773da2ea107ed';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        config(['payments.driver' => 'disabled']);
    }

    public function test_admin_finalizes_a_fee_for_the_application_being_viewed(): void
    {
        $application = $this->application(['email' => 'student@example.test']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('success');

        $paymentRequest = PaymentRequest::sole();
        $this->assertSame($application->id, $paymentRequest->application_id);
        $this->assertSame($admin->id, $paymentRequest->created_by);
        $this->assertSame('25.000', $paymentRequest->amount);
        $this->assertSame('BHD', $paymentRequest->currency);
        $this->assertSame('sent', $paymentRequest->status);
        $this->assertNotNull($paymentRequest->public_id);
        $this->assertNotNull($paymentRequest->sent_at);
        $this->assertTrue($paymentRequest->expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.created', 'subject_id' => $paymentRequest->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.sent', 'subject_id' => $paymentRequest->id]);

        Notification::assertSentOnDemand(PaymentRequestReady::class, function (PaymentRequestReady $notification, array $channels, object $notifiable) use ($application): bool {
            $mail = $notification->toMail($notifiable);
            $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return $notifiable->routes['mail'] === 'student@example.test'
                && $mail->subject === 'Payment Request for Your Application – '.$application->reference_number
                && str_contains($body, 'AFS')
                && str_contains((string) $mail->actionUrl, '/pay/request/'.$notification->paymentRequest->public_id.'/checkout');
        });
    }

    public function test_application_id_is_taken_from_route_binding_not_the_submitted_form(): void
    {
        $application = $this->application();
        $other = $this->application();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData() + ['application_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($application->id, PaymentRequest::sole()->application_id);
        $this->assertNotSame($other->id, PaymentRequest::sole()->application_id);
    }

    public function test_payment_form_has_no_manual_application_id_field(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin())
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Approve &amp; Send Payment Request', false)
            ->assertDontSee('name="application_id"', false);
    }

    public function test_email_uses_the_current_registered_address_after_an_edit(): void
    {
        $application = $this->application(['email' => 'old@example.test']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.applications.update', $application), [
            'status' => 'new',
            'full_name' => 'Updated Student',
            'email' => 'new@example.test',
            'phone' => '+973 39000000',
            'nationality' => 'Bahraini',
            'current_country' => 'Bahrain',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(PaymentRequestReady::class, fn (PaymentRequestReady $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'new@example.test');
    }

    public function test_signed_payment_request_page_is_read_only(): void
    {
        $application = $this->application();
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id]);

        $this->get($this->showUrl($paymentRequest))
            ->assertOk()
            ->assertSee($application->reference_number)
            ->assertSee($application->full_name)
            ->assertSee('BHD 25.000')
            ->assertSee('Pay securely via AFS')
            ->assertDontSee('name="amount"', false)
            ->assertDontSee('name="entered_reference"', false)
            ->assertDontSee('name="application_id"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_tampered_and_expired_signed_links_are_rejected(): void
    {
        $application = $this->application();
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id]);

        $this->get($this->showUrl($paymentRequest).'&amount=1.000')->assertForbidden();

        $expiredRequest = PaymentRequest::factory()->create([
            'application_id' => $application->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->get(URL::temporarySignedRoute('pay.request.show', now()->addMinutes(5), ['paymentRequest' => $expiredRequest->public_id]))
            ->assertStatus(410);

        $this->assertSame('expired', $expiredRequest->fresh()->status);

        $this->get(URL::temporarySignedRoute('pay.request.show', now()->subMinute(), ['paymentRequest' => $paymentRequest->public_id]))
            ->assertForbidden();
    }

    public function test_starting_payment_creates_one_payment_with_the_exact_request_amount_and_application(): void
    {
        $application = $this->application(['email' => 'student@example.test', 'phone' => '+973 39000000']);
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id, 'amount' => '25.000']);

        $this->post($this->payUrl($paymentRequest), ['amount' => '1.000'])
            ->assertRedirect();

        $payment = Payment::sole();
        $this->assertSame($application->id, $payment->application_id);
        $this->assertSame('25.000', $payment->amount);
        $this->assertSame('BHD', $payment->currency);
        $this->assertSame($application->full_name, $payment->customer_name);
        $this->assertSame('student@example.test', $payment->email);
        $this->assertSame('+973 39000000', $payment->phone);
        $this->assertSame($application->reference_number, $payment->entered_reference);
        $this->assertSame($payment->id, $paymentRequest->fresh()->payment_id);

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($payment->id, $paymentRequest->fresh()->payment_id);
    }

    public function test_afs_checkout_is_started_through_the_existing_gateway_for_the_request(): void
    {
        $this->configureAfs();
        $application = $this->application();
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id, 'amount' => '25.000']);

        Http::fake([
            'https://eu-test.oppwa.com/v1/checkouts' => Http::response([
                'id' => self::CHECKOUT_ID,
                'result' => ['code' => '000.200.100'],
            ]),
        ]);

        $response = $this->post($this->payUrl($paymentRequest));
        $response->assertRedirect();

        $payment = Payment::sole();
        $this->assertSame('afs', $payment->gateway);
        $this->assertSame(self::CHECKOUT_ID, $payment->gateway_reference);
        $this->assertSame($application->id, $payment->application_id);
        $this->assertSame('25.000', $payment->amount);

        Http::assertSent(fn (Request $request): bool => $request['amount'] === '25.00'
            && $request['currency'] === 'BHD'
            && $request['merchantTransactionId'] === $payment->transaction_reference);

        $this->get((string) $response->headers->get('Location'))
            ->assertOk()
            ->assertSee('https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId='.self::CHECKOUT_ID, false);
    }

    public function test_verified_webhook_settles_the_payment_request_and_application_without_duplicates(): void
    {
        $this->configureAfs();
        $application = $this->application();
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id, 'amount' => '25.000']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v1/checkouts')) {
                return Http::response(['id' => self::CHECKOUT_ID, 'result' => ['code' => '000.200.100']]);
            }

            $payment = Payment::query()->latest('id')->firstOrFail();

            return Http::response([
                'id' => self::PAYMENT_ID,
                'paymentType' => 'DB',
                'amount' => '25.000',
                'currency' => 'BHD',
                'merchantTransactionId' => $payment->transaction_reference,
                'result' => ['code' => '000.000.000'],
                'authentication' => ['entityId' => self::ENTITY_ID],
            ]);
        });

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $payment = Payment::sole();

        $this->sendWebhook($this->notification($payment))->assertOk();

        $payment->refresh();
        $paymentRequest->refresh();
        $application->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $paymentRequest->status);
        $this->assertNotNull($paymentRequest->paid_at);
        $this->assertSame('paid', $application->payment_status);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);

        $this->sendWebhook($this->notification($payment))->assertOk();
        $this->assertDatabaseCount('payment_events', 1);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
        $this->assertSame('paid', $paymentRequest->fresh()->status);
    }

    public function test_failed_payment_does_not_mark_the_application_or_request_as_paid(): void
    {
        $this->configureAfs();
        $application = $this->application();
        $paymentRequest = PaymentRequest::factory()->create(['application_id' => $application->id, 'amount' => '25.000']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v1/checkouts')) {
                return Http::response(['id' => self::CHECKOUT_ID, 'result' => ['code' => '000.200.100']]);
            }

            $payment = Payment::query()->latest('id')->firstOrFail();

            return Http::response([
                'id' => self::PAYMENT_ID,
                'paymentType' => 'DB',
                'amount' => '25.000',
                'currency' => 'BHD',
                'merchantTransactionId' => $payment->transaction_reference,
                'result' => ['code' => '800.100.153'],
                'authentication' => ['entityId' => self::ENTITY_ID],
            ]);
        });

        $this->post($this->payUrl($paymentRequest))->assertRedirect();
        $payment = Payment::sole();

        $this->sendWebhook($this->notification($payment))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('sent', $paymentRequest->fresh()->status);
        $this->assertNotSame('paid', $application->fresh()->payment_status);
        $this->assertNull($paymentRequest->fresh()->paid_at);
        Notification::assertNothingSent();
    }

    public function test_paid_and_cancelled_requests_cannot_start_another_payment(): void
    {
        $application = $this->application();
        $paid = PaymentRequest::factory()->paid()->create(['application_id' => $application->id]);
        $cancelled = PaymentRequest::factory()->cancelled()->create(['application_id' => $application->id]);

        $this->get($this->showUrl($paid))->assertOk()->assertSee('already been paid')->assertDontSee('Pay securely via AFS');
        $this->post($this->payUrl($paid))->assertStatus(410);

        $this->get($this->showUrl($cancelled))->assertStatus(410);
        $this->post($this->payUrl($cancelled))->assertStatus(410);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_reissuing_cancels_the_previous_unpaid_request_and_preserves_history(): void
    {
        $application = $this->application();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHasNoErrors();

        $first = PaymentRequest::sole();

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHasErrors('replace_existing');

        $this->assertSame(1, PaymentRequest::count());

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData() + ['replace_existing' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, PaymentRequest::count());
        $this->assertSame('cancelled', $first->fresh()->status);
        $second = PaymentRequest::query()->whereKeyNot($first->id)->sole();
        $this->assertSame('sent', $second->status);
        $this->assertNotSame($first->public_id, $second->public_id);

        $this->get($this->showUrl($first->fresh()))->assertStatus(410);
    }

    public function test_an_application_with_a_successful_payment_cannot_receive_another_request(): void
    {
        $application = $this->application(['payment_status' => 'paid']);

        $this->actingAs($this->admin())
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payment_requests', 0);
    }

    public function test_admin_can_resend_and_cancel_an_unpaid_request(): void
    {
        $application = $this->application();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.applications.payment-requests.store', $application), $this->feeData());
        $paymentRequest = PaymentRequest::sole();

        $this->travel(5)->minutes();
        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.resend', [$application, $paymentRequest]))
            ->assertSessionHas('success');

        $this->assertTrue($paymentRequest->fresh()->sent_at->greaterThan($paymentRequest->created_at));

        $this->actingAs($admin)
            ->post(route('admin.applications.payment-requests.cancel', [$application, $paymentRequest]))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $paymentRequest->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment_request.cancelled', 'subject_id' => $paymentRequest->id]);
    }

    public function test_email_failure_keeps_the_request_for_a_safe_retry(): void
    {
        $application = $this->application();
        Notification::swap(new class extends NotificationFake
        {
            public function send($notifiables, $notification)
            {
                throw new RuntimeException('Mail transport unavailable');
            }
        });

        $this->actingAs($this->admin())
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertSessionHas('error');

        $paymentRequest = PaymentRequest::sole();
        $this->assertSame('sent', $paymentRequest->status);
        $this->assertNull($paymentRequest->sent_at);
        $this->assertSame($application->id, $paymentRequest->application_id);
    }

    public function test_only_authorized_administrators_can_finalize_fees(): void
    {
        $application = $this->application();

        $this->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]))
            ->post(route('admin.applications.payment-requests.store', $application), $this->feeData())
            ->assertForbidden();

        $this->assertDatabaseCount('payment_requests', 0);
    }

    private function application(array $attributes = []): Application
    {
        return Application::factory()->create($attributes + [
            'service_type' => 'study_abroad',
            'email' => 'student@example.test',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** @return array<string, string> */
    private function feeData(): array
    {
        return [
            'amount' => '25.000',
            'description' => 'Agreed service fee',
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

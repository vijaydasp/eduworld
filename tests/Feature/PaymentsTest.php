<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use App\Services\Payments\GatewayCheckout;
use App\Services\Payments\InvalidPaymentWebhook;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\VerifiedPaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        config(['payments.driver' => 'disabled']);
    }

    public function test_disabled_checkout_records_only_pending_intent_without_linking_a_guessed_reference(): void
    {
        $application = $this->application();
        $response = $this->post(route('pay.store'), $this->paymentData() + [
            'entered_reference' => $application->reference_number,
            'application_id' => $application->id, 'status' => 'paid', 'amount_received' => '12.345',
        ]);
        $response->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('12.345', $payment->amount);
        $this->assertSame('0.000', $payment->amount_received);
        $this->assertNull($payment->application_id);
        $this->assertSame('pending', $application->fresh()->payment_status);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Your payment request is recorded');
        Notification::assertNothingSent();
    }

    public function test_payment_pages_render_and_admin_filters_handle_end_date_without_start_date(): void
    {
        $payment = Payment::factory()->create();
        $this->get(route('pay'))->assertOk()->assertSee('Online checkout is not yet available');
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->get(route('admin.payments.index', ['to' => now()->toDateString()]))->assertOk()->assertSee($payment->transaction_reference);
        $this->get(route('admin.payments.show', $payment))->assertOk();
        $this->get(route('admin.payments.offline.create'))->assertOk();
    }

    public function test_enabled_adapter_redirects_only_to_approved_checkout_and_keeps_payment_pending(): void
    {
        $this->enableTestGateway();
        config(['payments.checkout_hosts' => ['checkout.example.test']]);
        $this->post(route('pay.store'), $this->paymentData())->assertRedirect('https://checkout.example.test/session');
        $this->assertDatabaseHas('payments', ['gateway_reference' => 'test-session', 'status' => 'pending']);
        Notification::assertNothingSent();
    }

    public function test_payment_form_rejects_fraction_precision_and_non_positive_amounts(): void
    {
        foreach (['1.2345', '-1', '0', '1e3'] as $amount) {
            $this->post(route('pay.store'), array_replace($this->paymentData(), ['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_receipts_require_a_valid_signature_and_browser_status_cannot_settle_payment(): void
    {
        $payment = Payment::factory()->create();
        $this->get(route('pay.receipt', $payment))->assertForbidden();
        $url = URL::temporarySignedRoute('pay.receipt', now()->addMinutes(5), ['payment' => $payment->id, 'status' => 'paid']);
        $this->get($url)->assertOk()->assertSee('Your payment request is recorded')->assertDontSee('Payment successful');
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_disabled_gateway_never_accepts_a_paid_webhook(): void
    {
        $payment = Payment::factory()->create(['gateway' => 'disabled']);
        $this->postJson(route('pay.webhook', 'disabled'), $this->event($payment))->assertStatus(503);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_unverified_callback_cannot_write_payment_or_event(): void
    {
        $this->enableTestGateway();
        $payment = Payment::factory()->create();
        $this->postJson(route('pay.webhook', 'test-gateway'), $this->event($payment))->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
        Notification::assertNothingSent();
    }

    public function test_verified_payment_is_idempotent_and_confirmation_is_sent_once(): void
    {
        $this->enableTestGateway();
        $application = $this->application();
        $payment = Payment::factory()->create(['application_id' => $application->id]);
        $event = $this->event($payment);
        $this->sendWebhook($event)->assertOk();
        $this->sendWebhook($event)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('12.345', $payment->fresh()->amount_received);
        $this->assertSame('paid', $application->fresh()->payment_status);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.gateway_verified', 'subject_id' => $payment->id]);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
    }

    public function test_verified_callback_must_match_amount_currency_and_checkout_session(): void
    {
        $this->enableTestGateway();
        $payment = Payment::factory()->create();
        $this->sendWebhook(array_replace($this->event($payment), ['amount' => '99.000']))->assertUnprocessable();
        $this->sendWebhook(array_replace($this->event($payment), ['currency' => 'USD']))->assertUnprocessable();
        $this->sendWebhook(array_replace($this->event($payment), ['gateway_reference' => 'another-checkout']))->assertStatus(409);
        $this->sendWebhook(array_replace($this->event($payment), ['amount_received' => '10.000']))->assertUnprocessable();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_an_event_id_cannot_be_reused_with_changed_settlement_data(): void
    {
        $this->enableTestGateway();
        $payment = Payment::factory()->create();
        $this->sendWebhook($this->event($payment))->assertOk();
        $this->sendWebhook(array_replace($this->event($payment), ['status' => 'refunded', 'amount_received' => '0.000']))->assertStatus(409);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_partial_settlement_advances_and_late_failure_cannot_undo_paid_or_refunded(): void
    {
        $this->enableTestGateway();
        $payment = Payment::factory()->create();
        $this->sendWebhook(array_replace($this->event($payment), ['event_id' => 'partial', 'status' => 'partially_paid', 'amount_received' => '5.100']))->assertOk();
        $this->assertSame('partially_paid', $payment->fresh()->status);
        Notification::assertNothingSent();
        $this->sendWebhook($this->event($payment))->assertOk();
        $this->sendWebhook(array_replace($this->event($payment), ['event_id' => 'late-failure', 'status' => 'failed', 'amount_received' => '0.000']))->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->sendWebhook(array_replace($this->event($payment), ['event_id' => 'refund', 'status' => 'refunded', 'amount_received' => '0.000']))->assertOk();
        $this->sendWebhook(array_replace($this->event($payment), ['event_id' => 'late-success']))->assertOk();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('0.000', $payment->fresh()->amount_received);
        Notification::assertSentOnDemandTimes(PaymentConfirmed::class, 1);
    }

    public function test_content_managers_cannot_access_payments_and_admins_cannot_record_offline_money(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]));
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->post(route('admin.payments.offline.store'), $this->offlineData())->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_super_admin_offline_receipt_and_refund_are_distinct_and_audited(): void
    {
        $application = $this->application();
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->post(route('admin.payments.offline.store'), $this->offlineData() + ['application_reference' => $application->reference_number])->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame('offline', $payment->channel);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $application->fresh()->payment_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.offline_recorded']);
        $this->post(route('admin.payments.offline.refund', $payment), ['reason' => 'Bank refund confirmed REF123', 'confirmed' => '1'])->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('refunded', $application->fresh()->payment_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.offline_refunded']);
    }

    public function test_even_super_admin_cannot_refund_gateway_transaction_through_offline_action(): void
    {
        $payment = Payment::factory()->create(['status' => 'paid', 'amount_received' => '12.345']);
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'is_active' => true]));
        $this->post(route('admin.payments.offline.refund', $payment), ['reason' => 'Attempt to bypass provider verification', 'confirmed' => '1'])->assertStatus(409);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_authorized_admin_can_link_payment_to_application(): void
    {
        $application = $this->application();
        $payment = Payment::factory()->create(['status' => 'paid', 'amount_received' => '12.345']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->patch(route('admin.payments.link', $payment), ['application_reference' => $application->reference_number])->assertRedirect();
        $this->assertSame($application->id, $payment->fresh()->application_id);
        $this->assertSame('paid', $application->fresh()->payment_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.application_linked']);
    }

    private function enableTestGateway(): void
    {
        config(['payments.driver' => 'test-gateway', 'payments.gateways.test-gateway' => SignedTestGateway::class]);
    }

    private function paymentData(): array
    {
        return ['customer_name' => 'Test Applicant', 'phone' => '+97333640001', 'email' => 'payer@example.test', 'service' => 'other', 'amount' => '12.345', 'terms' => '1'];
    }

    private function offlineData(): array
    {
        return $this->paymentData() + ['amount_received' => '12.345', 'offline_reference' => 'BANK-123', 'remarks' => 'Transfer receipt checked against statement.'];
    }

    private function application(): Application
    {
        return Application::create([
            'reference_number' => 'EW-2026-00001', 'service_type' => 'other', 'full_name' => 'Test Applicant',
            'nationality' => 'Bahraini', 'current_country' => 'Bahrain', 'phone' => '+97333640001',
            'email' => 'applicant@example.test', 'consent_accuracy' => true, 'consent_processing' => true, 'consented_at' => now(),
        ]);
    }

    private function event(Payment $payment): array
    {
        return ['event_id' => 'settlement-1', 'transaction_reference' => $payment->transaction_reference, 'gateway_reference' => $payment->gateway_reference, 'status' => 'paid', 'amount' => $payment->amount, 'amount_received' => $payment->amount, 'currency' => 'BHD'];
    }

    private function sendWebhook(array $event): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('pay.webhook', 'test-gateway'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => hash_hmac('sha256', $body, 'test-only-signing-key')], $body);
    }
}

/** A test-only signed adapter; never registered in application configuration. */
final class SignedTestGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'test-gateway';
    }

    public function available(): bool
    {
        return true;
    }

    public function initiate(Payment $payment, string $returnUrl): GatewayCheckout
    {
        return new GatewayCheckout('https://checkout.example.test/session', 'test-session');
    }

    public function verifyWebhook(Request $request): VerifiedPaymentEvent
    {
        $signature = hash_hmac('sha256', $request->getContent(), 'test-only-signing-key');
        if (! hash_equals($signature, (string) $request->header('X-Test-Signature'))) {
            throw new InvalidPaymentWebhook;
        }
        $data = $request->json()->all();

        return new VerifiedPaymentEvent($data['event_id'], $data['transaction_reference'], $data['gateway_reference'], $data['status'], $data['amount'], $data['amount_received'], $data['currency']);
    }
}

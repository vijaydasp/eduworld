<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use App\Notifications\NewApplication;
use App\Notifications\NewEnquiry;
use App\Notifications\PaymentConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_public_pages_render_and_sitemap_excludes_drafts(): void
    {
        $draft = Post::factory()->create(['is_published' => false, 'slug' => 'private-draft']);
        foreach (['/', '/services', '/apply', '/contact', '/news', '/pay', '/privacy-policy', '/terms', '/payment-terms', '/refund-policy', '/disclaimer', '/admin/login'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertDontSee($draft->slug);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
    }

    public function test_sign_in_is_audited_and_logout_ends_access(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Strong-password-123!')]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Strong-password-123!'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'admin.login']);
        $this->get('/admin/dashboard')->assertOk();
        $this->post('/admin/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/admin/applications')->assertRedirect(route('login'));
    }

    public function test_invalid_or_disabled_accounts_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('Strong-password-123!')]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Strong-password-123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/admin/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    public function test_repeated_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', ['email' => 'rate-limit@example.test', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/admin/login', ['email' => 'rate-limit@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_disabling_an_account_revokes_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_roles_have_only_their_assigned_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/applications')->assertOk();
        foreach (['/admin/posts', '/admin/settings', '/admin/users', '/admin/audit'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $editor = User::factory()->create(['role' => 'content_manager']);
        $this->actingAs($editor)->get('/admin/dashboard')->assertOk();
        $this->get('/admin/posts')->assertOk();
        foreach (['/admin/applications', '/admin/enquiries', '/admin/payments', '/admin/settings', '/admin/users'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $super = User::factory()->create(['role' => 'super_admin']);
        foreach (['/admin/dashboard', '/admin/settings', '/admin/users', '/admin/users/create', '/admin/audit'] as $path) {
            $this->actingAs($super)->get($path)->assertOk();
        }
    }

    public function test_settings_update_invalidates_cache_and_changes_public_contact_information(): void
    {
        Setting::getValue('phone');
        $user = User::factory()->create(['role' => 'super_admin']);
        $data = collect(config('website'))->map(fn ($field) => $field['default'])->all();
        $data['phone'] = '+973 17000000';
        $data['company_name'] = 'Edu World <script>alert(1)</script>';
        $data['map_url'] = 'https://www.google.com/maps?q=Manama&output=embed';
        $this->actingAs($user)->put('/admin/settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('+973 17000000', Setting::getValue('phone'));
        $this->get('/contact')->assertOk()->assertSee('+973 17000000')->assertSee('q=Manama&amp;output=embed', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.updated']);
        $data['map_url'] = 'https://malicious.example/maps';
        $this->put('/admin/settings', $data)->assertSessionHasErrors('map_url');
    }

    public function test_user_creation_hashes_password_and_never_audits_it(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $data = ['name' => 'Content Team', 'email' => 'editor@example.test', 'role' => 'content_manager', 'is_active' => 1, 'password' => 'Secure-editor-123!', 'password_confirmation' => 'Secure-editor-123!'];
        $this->actingAs($super)->post('/admin/users', $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $created = User::where('email', $data['email'])->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $created->password));
        $this->assertStringNotContainsString($data['password'], ActivityLog::all()->toJson());
        $this->get(route('admin.users.edit', $created))->assertOk();
    }

    public function test_last_active_super_admin_cannot_be_removed(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->put(route('admin.users.update', $super), ['name' => $super->name, 'email' => $super->email, 'role' => 'admin', 'is_active' => 0])->assertSessionHasErrors('role');
        $this->assertSame('super_admin', $super->fresh()->role);
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_captcha_rejects_failed_verification_without_storing_an_enquiry(): void
    {
        config(['eduworld.turnstile_secret_key' => 'test-only-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $this->post('/contact', ['cf-turnstile-response' => 'invalid-test-token'])->assertSessionHasErrors('captcha');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_admin_pages_are_not_cached_or_indexed(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_notification_templates_render_with_branding_and_correct_receipts(): void
    {
        $application = Application::factory()->create();
        $payment = Payment::factory()->create(['channel' => 'offline', 'status' => 'paid', 'amount_received' => '12.345']);
        $enquiry = Enquiry::create(['full_name' => 'Test Applicant', 'phone' => '+97333640001', 'service_type' => 'study_abroad', 'message' => 'Please help me select a course.', 'status' => 'new']);
        $notifications = [new ApplicationReceived($application), new NewApplication($application), new NewEnquiry($enquiry), new PaymentConfirmed($payment)];
        foreach ($notifications as $notification) {
            $html = (string) $notification->toMail(new AnonymousNotifiable)->render();
            $this->assertStringContainsString('Edu World', $html);
            $this->assertStringContainsString('#0A2A66', $html);
            $this->assertStringNotContainsString('Laravel Logo', $html);
        }
        $this->assertStringContainsString('12.345', $html);
        $this->assertStringContainsString('offline payment', $html);
    }

    public function test_production_rejects_untrusted_host_headers(): void
    {
        $this->app['env'] = 'production';
        try {
            $this->get('https://untrusted.example/')->assertStatus(400);
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    public function test_production_accepts_the_hostname_configured_for_the_deployment(): void
    {
        $this->app['env'] = 'production';
        $deploymentUrl = 'https://eduworld.osmiuminfotech.com';

        try {
            config(['app.url' => 'https://eduworld.bh']);
            $this->get($deploymentUrl.'/')->assertStatus(400);

            config(['app.url' => $deploymentUrl]);
            $this->get($deploymentUrl.'/')->assertOk()
                ->assertSee('href="'.$deploymentUrl.'/"', false);
        } finally {
            Request::setTrustedHosts([]);
        }
    }
}

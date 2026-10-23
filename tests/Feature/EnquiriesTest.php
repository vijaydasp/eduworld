<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\NewEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnquiriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        config(['applications.admin_email' => 'admin@example.test']);
    }

    private function payload(): array
    {
        return ['full_name' => 'Enquiry Visitor', 'phone' => '+973 33640001', 'email' => 'visitor@example.test', 'service_type' => 'study_abroad', 'message' => 'I would like guidance on applying for a degree abroad.'];
    }

    public function test_contact_form_persists_an_enquiry_and_notifies_admin(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('Send us an enquiry');
        $this->post(route('contact.store'), $this->payload())->assertRedirect(route('contact'))->assertSessionHas('success');
        $this->assertDatabaseHas('enquiries', ['full_name' => 'Enquiry Visitor', 'status' => 'new']);
        Notification::assertSentOnDemand(NewEnquiry::class);
    }

    public function test_honeypot_and_invalid_contact_payload_are_rejected(): void
    {
        $this->post(route('contact.store'), $this->payload() + ['website' => 'spam'])->assertSessionHasErrors('website');
        $this->post(route('contact.store'), ['message' => 'Hello'])->assertSessionHasErrors(['full_name', 'phone', 'service_type', 'message']);
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_enquiry_management_requires_permission_and_records_follow_up(): void
    {
        $enquiry = Enquiry::factory()->create();
        $this->get(route('admin.enquiries.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]))->get(route('admin.enquiries.show', $enquiry))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get(route('admin.enquiries.show', $enquiry))->assertOk();
        $this->patch(route('admin.enquiries.update', $enquiry), ['status' => 'resolved', 'admin_notes' => 'Called and answered questions.'])->assertRedirect()->assertSessionHasNoErrors();
        $enquiry->refresh();
        $this->assertNotNull($enquiry->contacted_at);
        $this->assertNotNull($enquiry->resolved_at);
        $this->assertSame('resolved', $enquiry->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'enquiry.updated', 'subject_id' => $enquiry->id]);
    }
}

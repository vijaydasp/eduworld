<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use App\Notifications\NewApplication;
use App\Services\Applications\ReferenceGenerator;
use App\Services\Applications\SubmitApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Storage::fake('private');
        config(['applications.admin_email' => 'admin@example.test']);
    }

    private function payload(string $service = 'study_abroad', array $details = ['preferred_course' => 'Computer Science']): array
    {
        return ['service_type' => $service, 'full_name' => 'Asha Applicant', 'nationality' => 'Indian', 'current_country' => 'Bahrain', 'phone' => '+973 33640001', 'email' => 'asha@example.test', 'consent_accuracy' => '1', 'consent_processing' => '1', 'details' => $details];
    }

    public static function services(): array
    {
        return [
            'study' => ['study_abroad', ['preferred_course' => 'Medicine'], 'study_abroad_details'],
            'job' => ['job_assistance', ['job_category' => 'Healthcare & Nursing'], 'job_assistance_details'],
            'visa' => ['visa_services', ['destination_country' => 'France', 'visa_type' => 'Tourist / Visit'], 'visa_application_details'],
            'support' => ['supporting_documents', ['services_requested' => ['Flight Itinerary', 'Cover Letter']], 'supporting_document_details'],
            'attestation' => ['attestation', ['document_type' => 'Degree certificate', 'issuing_country' => 'India', 'destination_country' => 'Bahrain'], 'attestation_details'],
            'employer' => ['employer_request', ['company_name' => 'Example Employer', 'company_country' => 'Bahrain', 'roles_required' => 'Warehouse workers'], 'employer_requests'],
            'other' => ['other', ['remarks' => 'Please advise on my travel documents.'], null],
        ];
    }

    #[DataProvider('services')]
    public function test_applicants_can_submit_each_service_and_receive_a_reference(string $service, array $details, ?string $table): void
    {
        $this->post(route('apply.store'), $this->payload($service, $details))->assertSessionHasNoErrors()->assertRedirect(route('apply.success'));
        $application = Application::query()->sole();
        $this->assertSame('EW-'.now()->year.'-00001', $application->reference_number);
        $this->assertSame('new', $application->status);
        $this->assertTrue($application->consent_accuracy);
        $this->assertTrue($application->consent_processing);
        $this->assertNotNull($application->consented_at);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'to_status' => 'new']);
        if ($table) {
            $this->assertDatabaseHas($table, ['application_id' => $application->id]);
        }
        Notification::assertSentOnDemand(ApplicationReceived::class);
        Notification::assertSentOnDemand(NewApplication::class);
        $this->get(route('apply.success'))->assertOk()->assertSee($application->reference_number);
    }

    public function test_conditional_fields_and_consents_are_validated_server_side(): void
    {
        $data = $this->payload('visa_services', ['preferred_course' => 'Not a visa field']);
        unset($data['consent_processing']);
        $this->post(route('apply.store'), $data)->assertSessionHasErrors(['details', 'details.destination_country', 'details.visa_type', 'consent_processing']);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_service_arrays_are_rejected_without_a_server_error(): void
    {
        $data = $this->payload();
        $data['service_type'] = ['study_abroad'];
        $this->post(route('apply.store'), $data)->assertSessionHasErrors('service_type');
    }

    public function test_reference_sequences_are_unique_reset_each_year_and_rollback_cleanly(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6));
        $submit = app(SubmitApplication::class);
        $first = $submit->handle($this->payload());
        $second = $submit->handle($this->payload());
        $this->assertSame('EW-2026-00001', $first->reference_number);
        $this->assertSame('EW-2026-00002', $second->reference_number);
        try {
            DB::transaction(function (): void {
                app(ReferenceGenerator::class)->next();
                throw new RuntimeException('Simulate failure');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame('EW-2026-00003', $submit->handle($this->payload())->reference_number);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('EW-2027-00001', $submit->handle($this->payload())->reference_number);
    }

    public function test_uploads_are_private_and_download_requires_an_authorized_admin(): void
    {
        $data = $this->payload();
        $data['documents'] = ['passport' => [UploadedFile::fake()->create('passport.pdf', 8, 'application/pdf')]];
        $this->post(route('apply.store'), $data)->assertSessionHasNoErrors();
        $document = ApplicationDocument::query()->sole();
        Storage::disk('private')->assertExists($document->path);
        $this->assertNotSame('passport.pdf', basename($document->path));
        $this->get(route('admin.documents.download', $document))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]))->get(route('admin.documents.download', $document))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get(route('admin.documents.download', $document))->assertOk()->assertDownload('passport.pdf');
        $this->assertDatabaseHas('activity_logs', ['action' => 'application.document_downloaded', 'subject_id' => $document->id]);
    }

    public function test_mismatched_mime_and_oversized_documents_are_rejected(): void
    {
        $data = $this->payload();
        $data['documents'] = ['passport' => [UploadedFile::fake()->create('passport.jpg', 8, 'application/pdf')]];
        $this->post(route('apply.store'), $data)->assertSessionHasErrors('documents.passport.0');
        $data['documents'] = ['passport' => [UploadedFile::fake()->create('passport.pdf', 10241, 'application/pdf')]];
        $this->post(route('apply.store'), $data)->assertSessionHasErrors('documents.passport.0');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_upload_metadata_failure_rolls_back_data_and_deletes_the_file(): void
    {
        ApplicationDocument::creating(function (): void {
            throw new RuntimeException('Simulated database failure');
        });
        $data = $this->payload();
        $data['documents'] = ['passport' => [UploadedFile::fake()->create('passport.pdf', 8, 'application/pdf')]];
        try {
            app(SubmitApplication::class)->handle($data);
            $this->fail('A failed document record must fail the submission.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated database failure', $error->getMessage());
        } finally {
            ApplicationDocument::flushEventListeners();
        }
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_status_updates_keep_history_and_cannot_change_payment_status(): void
    {
        $application = Application::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => true]))->patch(route('admin.applications.update', $application), ['status' => 'completed'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('admin.applications.update', $application), ['status' => 'under_review', 'status_note' => 'Passport needs review.', 'admin_notes' => 'Call tomorrow.', 'payment_status' => 'paid'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'under_review', 'payment_status' => 'pending', 'admin_notes' => 'Call tomorrow.']);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'user_id' => $admin->id, 'from_status' => 'new', 'to_status' => 'under_review', 'notes' => 'Passport needs review.']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'application.updated', 'subject_id' => $application->id]);
    }

    public function test_admin_filters_isolate_matching_applications(): void
    {
        Application::factory()->create(['full_name' => 'Matching Applicant', 'current_country' => 'Bahrain', 'nationality' => 'Indian', 'payment_status' => 'paid']);
        Application::factory()->create(['full_name' => 'Excluded Applicant', 'current_country' => 'Oman', 'nationality' => 'Nepali']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get(route('admin.applications.index', ['q' => 'Applicant', 'country' => 'Bahrain', 'nationality' => 'Indian', 'payment_status' => 'paid', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]))->assertOk()->assertSee('Matching Applicant')->assertDontSee('Excluded Applicant');
    }

    public function test_success_page_requires_the_submission_session(): void
    {
        $this->get(route('apply.success'))->assertNotFound();
    }

    public function test_application_form_and_admin_detail_render(): void
    {
        $this->get(route('apply', ['service' => 'job_assistance']))->assertOk()->assertSee('Job Assistance');
        $application = Application::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get(route('admin.applications.show', $application))->assertOk()->assertSee($application->reference_number);
    }
}

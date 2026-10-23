<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->string('service_type', 40)->index();
            $table->string('full_name', 150);
            $table->string('nationality', 100)->index();
            $table->date('date_of_birth')->nullable();
            $table->string('current_country', 100)->index();
            $table->string('current_city', 100)->nullable();
            $table->string('phone', 30)->index();
            $table->string('email', 254)->index();
            $table->string('preferred_contact_method', 20)->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->string('payment_status', 30)->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('consent_accuracy');
            $table->boolean('consent_processing');
            $table->timestamp('consented_at');
            $table->string('consent_version', 30)->default('2026-10-06');
            $table->timestamps();
            $table->index('created_at');
        });
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('disk', 30)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $detailTables = [
            'study_abroad_details' => ['highest_qualification', 'year_completed', 'institution', 'preferred_course', 'preferred_study_level', 'preferred_countries', 'approximate_budget', 'preferred_intake', 'english_test'],
            'job_assistance_details' => ['current_occupation', 'job_category', 'years_experience', 'current_employer', 'gcc_residency', 'driving_license', 'license_category', 'preferred_country', 'english_level', 'other_languages'],
            'visa_application_details' => ['destination_country', 'visa_type', 'purpose_of_travel', 'proposed_travel_date', 'duration', 'current_residency', 'previous_visa_history'],
            'supporting_document_details' => ['destination', 'travel_start_date', 'travel_end_date', 'number_of_travelers', 'remarks'],
            'attestation_details' => ['document_type', 'issuing_country', 'destination_country', 'purpose', 'number_of_documents'],
            'employer_requests' => ['company_name', 'company_country', 'industry', 'roles_required', 'number_of_candidates', 'work_location', 'preferred_start_date', 'requirements'],
        ];
        foreach ($detailTables as $name => $fields) {
            Schema::create($name, function (Blueprint $table) use ($name, $fields) {
                $table->id();
                $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
                foreach ($fields as $field) {
                    if (in_array($field, ['year_completed', 'years_experience', 'number_of_travelers', 'number_of_documents', 'number_of_candidates'])) {
                        $table->unsignedSmallInteger($field)->nullable();
                    } elseif (str_ends_with($field, '_date')) {
                        $table->date($field)->nullable();
                    } elseif (in_array($field, ['purpose_of_travel', 'previous_visa_history', 'remarks', 'purpose', 'roles_required', 'requirements'])) {
                        $table->text($field)->nullable();
                    } else {
                        $table->string($field)->nullable();
                    }
                }
                if ($name === 'supporting_document_details') {
                    $table->json('services_requested');
                }
                $table->timestamps();
            });
        }
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->string('email', 254)->nullable()->index();
            $table->string('service_type', 40)->index();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['enquiries', 'employer_requests', 'attestation_details', 'supporting_document_details', 'visa_application_details', 'job_assistance_details', 'study_abroad_details', 'application_status_histories', 'application_documents', 'applications', 'application_sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

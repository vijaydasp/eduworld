<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    protected $fillable = ['reference_number', 'service_type', 'full_name', 'nationality', 'date_of_birth', 'current_country', 'current_city', 'phone', 'email', 'preferred_contact_method', 'status', 'payment_status', 'admin_notes', 'remarks', 'consent_accuracy', 'consent_processing', 'consented_at', 'consent_version'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'consented_at' => 'datetime', 'consent_accuracy' => 'boolean', 'consent_processing' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function studyDetails(): HasOne
    {
        return $this->hasOne(StudyAbroadDetail::class);
    }

    public function jobDetails(): HasOne
    {
        return $this->hasOne(JobAssistanceDetail::class);
    }

    public function visaDetails(): HasOne
    {
        return $this->hasOne(VisaApplicationDetail::class);
    }

    public function supportingDocumentDetails(): HasOne
    {
        return $this->hasOne(SupportingDocumentDetail::class);
    }

    public function attestationDetails(): HasOne
    {
        return $this->hasOne(AttestationDetail::class);
    }

    public function employerRequest(): HasOne
    {
        return $this->hasOne(EmployerRequest::class);
    }

    public function serviceLabel(): string
    {
        return config('applications.services.'.$this->service_type.'.label', $this->service_type);
    }
}

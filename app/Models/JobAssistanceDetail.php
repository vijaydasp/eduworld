<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAssistanceDetail extends Model
{
    protected $fillable = ['current_occupation', 'job_category', 'years_experience', 'current_employer', 'gcc_residency', 'driving_license', 'license_category', 'preferred_country', 'english_level', 'other_languages'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

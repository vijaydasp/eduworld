<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyAbroadDetail extends Model
{
    protected $fillable = ['highest_qualification', 'year_completed', 'institution', 'preferred_course', 'preferred_study_level', 'preferred_countries', 'approximate_budget', 'preferred_intake', 'english_test'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployerRequest extends Model
{
    protected $fillable = ['company_name', 'company_country', 'industry', 'roles_required', 'number_of_candidates', 'work_location', 'preferred_start_date', 'requirements'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaApplicationDetail extends Model
{
    protected $fillable = ['destination_country', 'visa_type', 'purpose_of_travel', 'proposed_travel_date', 'duration', 'current_residency', 'previous_visa_history'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

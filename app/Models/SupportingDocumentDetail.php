<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportingDocumentDetail extends Model
{
    protected $fillable = ['services_requested', 'destination', 'travel_start_date', 'travel_end_date', 'number_of_travelers', 'remarks'];

    protected function casts(): array
    {
        return ['services_requested' => 'array'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

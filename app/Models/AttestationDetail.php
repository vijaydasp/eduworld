<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttestationDetail extends Model
{
    protected $fillable = ['document_type', 'issuing_country', 'destination_country', 'purpose', 'number_of_documents'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

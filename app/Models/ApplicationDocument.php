<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $fillable = ['document_type', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

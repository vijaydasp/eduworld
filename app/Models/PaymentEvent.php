<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = ['payment_id', 'gateway', 'event_id', 'fingerprint', 'status', 'result'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'paid', 'partially_paid', 'failed', 'refunded'];

    protected $fillable = [
        'application_id', 'transaction_reference', 'entered_reference', 'gateway',
        'gateway_reference', 'channel', 'customer_name', 'phone', 'email', 'service',
        'amount', 'amount_received', 'currency', 'status', 'remarks', 'gateway_response',
        'paid_at', 'refunded_at', 'recorded_by', 'offline_reference',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'amount_received' => 'decimal:3', 'gateway_response' => 'array', 'paid_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}

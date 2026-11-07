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

    /**
     * Selectable payment services: application services, standalone fee types,
     * with the catch-all "Other" option kept last.
     *
     * @return array<string, string>
     */
    public static function serviceOptions(): array
    {
        $options = collect(config('applications.services'))
            ->map(fn (array $service): string => $service['label'])
            ->all();

        $other = $options['other'] ?? null;
        unset($options['other']);

        $options = array_merge($options, config('payments.fee_services'));

        if ($other !== null) {
            $options['other'] = $other;
        }

        return $options;
    }

    public function serviceLabel(): string
    {
        return static::serviceOptions()[$this->service] ?? (string) $this->service;
    }

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

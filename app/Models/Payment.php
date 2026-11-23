<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'paid_at', 'refunded_at', 'recorded_by', 'offline_reference', 'provider_payment_type',
        'provider_payment_brand', 'provider_result_code', 'provider_transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'amount_received' => 'decimal:3',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'provider_transaction_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Match the application reference the customer typed into the old public form.
     * This is a display/reconciliation hint only; it never binds the payment for
     * authorization or settlement because customer input is not verified linking.
     */
    public function enteredApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'entered_reference', 'reference_number');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function paymentRequest(): HasOne
    {
        return $this->hasOne(PaymentRequest::class);
    }
}

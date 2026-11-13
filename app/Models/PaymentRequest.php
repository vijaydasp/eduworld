<?php

namespace App\Models;

use Database\Factories\PaymentRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentRequest extends Model
{
    /** @use HasFactory<PaymentRequestFactory> */
    use HasFactory;

    public const TYPE_APPLICATION = 'application';

    public const TYPE_STANDALONE = 'standalone';

    public const TYPES = [self::TYPE_APPLICATION, self::TYPE_STANDALONE];

    public const STATUSES = ['sent', 'paid', 'expired', 'cancelled'];

    protected $fillable = [
        'public_id', 'type', 'application_id', 'payment_id', 'customer_name',
        'customer_email', 'customer_phone', 'amount', 'currency', 'description',
        'status', 'expires_at', 'sent_at', 'paid_at', 'created_by',
    ];

    protected $attributes = [
        'type' => self::TYPE_APPLICATION,
        'currency' => 'BHD',
        'status' => 'sent',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentRequest $paymentRequest): void {
            $paymentRequest->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeApplicationRequests(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_APPLICATION);
    }

    public function scopeStandaloneRequests(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_STANDALONE);
    }

    public function isApplication(): bool
    {
        return ($this->type ?: self::TYPE_APPLICATION) === self::TYPE_APPLICATION;
    }

    public function isStandalone(): bool
    {
        return $this->type === self::TYPE_STANDALONE;
    }

    public function recipientEmail(): ?string
    {
        $email = $this->isStandalone()
            ? $this->customer_email
            : $this->application?->email;

        return filled($email) ? (string) $email : null;
    }

    public function recipientName(): string
    {
        $name = $this->isStandalone()
            ? $this->customer_name
            : $this->application?->full_name;

        return filled($name) ? (string) $name : 'Customer';
    }

    public function typeLabel(): string
    {
        return $this->isStandalone() ? 'Standalone request' : 'Application request';
    }

    public function isPayable(): bool
    {
        return $this->status === 'sent' && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }
}

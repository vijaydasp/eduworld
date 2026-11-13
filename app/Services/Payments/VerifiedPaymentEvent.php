<?php

namespace App\Services\Payments;

use InvalidArgumentException;

final readonly class VerifiedPaymentEvent
{
    public function __construct(
        public string $eventId,
        public string $transactionReference,
        public string $gatewayReference,
        public string $status,
        public string $amount,
        public string $amountReceived,
        public string $currency,
        public ?string $providerReference = null,
    ) {
        if ($eventId === '' || strlen($eventId) > 150 || $transactionReference === '' || $gatewayReference === '' || strlen($gatewayReference) > 150 || ! in_array($status, ['paid', 'partially_paid', 'failed', 'refunded'], true)) {
            throw new InvalidArgumentException('Invalid verified payment event.');
        }

        if ($providerReference !== null && ($providerReference === '' || strlen($providerReference) > 150)) {
            throw new InvalidArgumentException('Invalid provider reference.');
        }

        BhdAmount::minorUnits($amount);
        BhdAmount::minorUnits($amountReceived);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->transactionReference, $this->gatewayReference, $this->status,
            BhdAmount::normalize($this->amount), BhdAmount::normalize($this->amountReceived), $this->currency,
            $this->providerReference,
        ], JSON_THROW_ON_ERROR));
    }
}

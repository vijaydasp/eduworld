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
        public ?string $paymentType = null,
        public ?string $paymentBrand = null,
        public ?string $resultCode = null,
        public ?string $transactionTimestamp = null,
        public ?string $resultDescription = null,
        public ?string $providerEntityId = null,
        public array $threeDSecureDetails = [],
    ) {
        if ($eventId === '' || strlen($eventId) > 150 || $transactionReference === '' || $gatewayReference === '' || strlen($gatewayReference) > 150 || ! in_array($status, ['paid', 'partially_paid', 'failed', 'review_required', 'refunded'], true)) {
            throw new InvalidArgumentException('Invalid verified payment event.');
        }

        if ($providerReference !== null && ($providerReference === '' || strlen($providerReference) > 150)) {
            throw new InvalidArgumentException('Invalid provider reference.');
        }

        if ($resultDescription !== null && strlen($resultDescription) > 1000) {
            throw new InvalidArgumentException('Invalid provider result description.');
        }

        BhdAmount::minorUnits($amount);
        BhdAmount::minorUnits($amountReceived);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->transactionReference, $this->gatewayReference, $this->status,
            BhdAmount::normalize($this->amount), BhdAmount::normalize($this->amountReceived), $this->currency,
            $this->providerReference, $this->paymentType, $this->paymentBrand, $this->resultCode,
            $this->transactionTimestamp, $this->resultDescription, $this->providerEntityId,
            $this->threeDSecureDetails,
        ], JSON_THROW_ON_ERROR));
    }
}

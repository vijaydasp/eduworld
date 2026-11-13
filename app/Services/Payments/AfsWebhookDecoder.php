<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use JsonException;

/**
 * Decrypts AFS webhook notifications.
 *
 * AFS delivers webhook bodies as AES-256-GCM ciphertext. The 64-character hexadecimal
 * secret is configured in the AFS portal (Administration > Webhooks). The initialization
 * vector and authentication tag are sent in the X-Initialization-Vector and
 * X-Authentication-Tag headers.
 *
 * @see https://afs.docs.oppwa.com/tutorials/webhooks
 */
final class AfsWebhookDecoder
{
    /** @return array<string, mixed> */
    public function decode(Request $request): array
    {
        $secret = (string) config('payments.afs.webhook_secret');

        if (preg_match('/\A[0-9a-f]{64}\z/i', $secret) !== 1) {
            throw new InvalidPaymentWebhook('The AFS webhook secret is not configured.');
        }

        $iv = $this->hex($this->header($request, 'X-Initialization-Vector'), 'initialization vector', 12, 16);
        $tag = $this->hex($this->header($request, 'X-Authentication-Tag'), 'authentication tag', 16, 16);
        $ciphertext = $this->hex($this->body($request), 'encrypted body', 2, 2_000_000);
        $tag = hex2bin($tag);

        $plaintext = @openssl_decrypt(
            hex2bin($ciphertext),
            'aes-256-gcm',
            hex2bin($secret),
            OPENSSL_RAW_DATA,
            hex2bin($iv),
            $tag,
        );

        if ($plaintext === false) {
            throw new InvalidPaymentWebhook('The AFS webhook payload could not be authenticated.');
        }

        try {
            $payload = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidPaymentWebhook('The AFS webhook payload is not valid JSON.');
        }

        if (! is_array($payload)) {
            throw new InvalidPaymentWebhook('The AFS webhook payload has an unexpected structure.');
        }

        return $payload;
    }

    private function body(Request $request): string
    {
        if (! $request->isJson()) {
            return trim($request->getContent());
        }

        $wrapped = $request->json()->all();
        $body = $wrapped['encryptedBody'] ?? null;

        if (! is_string($body)) {
            throw new InvalidPaymentWebhook('The AFS webhook body is missing.');
        }

        return trim($body);
    }

    private function header(Request $request, string $name): string
    {
        $value = $request->header($name);

        if (! is_string($value) || $value === '') {
            throw new InvalidPaymentWebhook('The AFS webhook is missing its encryption headers.');
        }

        return trim($value);
    }

    /** Validates and returns a hexadecimal string whose byte length is within the given bounds. */
    private function hex(string $value, string $label, int $minimumBytes, int $maximumBytes): string
    {
        if ($value === '' || strlen($value) % 2 !== 0 || preg_match('/\A[0-9a-f]+\z/i', $value) !== 1) {
            throw new InvalidPaymentWebhook('The AFS webhook '.$label.' is malformed.');
        }

        $bytes = intdiv(strlen($value), 2);

        if ($bytes < $minimumBytes || $bytes > $maximumBytes) {
            throw new InvalidPaymentWebhook('The AFS webhook '.$label.' has an unexpected length.');
        }

        return $value;
    }
}

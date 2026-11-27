<?php

namespace App\Services\Payments;

/**
 * Classifies AFS/OPPWA result codes into the settlement states used by this application.
 *
 * Only codes that the provider documents as a completed success are allowed to settle a
 * payment. Pending, review and unknown codes are deliberately left unsettled.
 *
 * @see https://afs.docs.oppwa.com/reference/resultCodes
 */
final class AfsResultCode
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REVIEW_REQUIRED = 'review_required';

    public const PENDING = 'pending';

    /**
     * Documented success codes: 000.000.*, 000.100.1*, 000.3*, 000.6*,
     * frictionless/data-only authentication success and 000.500.*.
     */
    private const SUCCESS_PATTERN = '/\A(?:000\.000\.|000\.100\.1|000\.[36]|000\.400\.(?:110|120)|000\.500\.)/';

    /**
     * Provider-documented hard declines and rejected authorization/3DS outcomes.
     * Technical, ambiguous, risk-review, and unknown codes remain pending.
     */
    private const FAILURE_PATTERN = '/\A(?:800\.[17]00|800\.800\.[123]|000\.400\.(?:1[0-9][1-9]|2))/';

    /**
     * Open sessions and delayed finalization. Technical/API errors are also left
     * pending by default instead of being treated as a definitive payment failure.
     */
    private const PENDING_PATTERN = '/\A(?:000\.200\.|800\.400\.5|100\.400\.500)/';

    private const REVIEW_PATTERN = '/\A000\.400\.(?:0(?!3)\d{2}|100)\z/';

    public static function classify(?string $code): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return self::PENDING;
        }

        if (preg_match(self::REVIEW_PATTERN, $code) === 1) {
            return self::REVIEW_REQUIRED;
        }

        if (preg_match(self::PENDING_PATTERN, $code) === 1) {
            return self::PENDING;
        }

        if (preg_match(self::SUCCESS_PATTERN, $code) === 1) {
            return self::PAID;
        }

        if (preg_match(self::FAILURE_PATTERN, $code) === 1) {
            return self::FAILED;
        }

        return self::PENDING;
    }

    /** AFS indicates a prepared checkout with one of these documented result codes. */
    public static function isCheckoutCreated(?string $code): bool
    {
        $code = trim((string) $code);

        return in_array($code, ['000.200.100', '000.200.101'], true);
    }
}

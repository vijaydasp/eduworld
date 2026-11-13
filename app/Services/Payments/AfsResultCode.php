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

    public const PENDING = 'pending';

    /**
     * Documented success codes: 000.000.*, 000.100.1*, 000.3*, 000.6*,
     * frictionless/data-only authentication success and 000.500.*.
     */
    private const SUCCESS_PATTERN = '/\A(?:000\.000\.|000\.100\.1|000\.[36]|000\.400\.(?:110|120)|000\.500\.)/';

    /**
     * Hard declines, validation/configuration errors, risk rejections and communication errors.
     * Success-with-review codes such as 000.400.020 are intentionally excluded.
     */
    private const FAILURE_PATTERN = '/\A(?:800\.|900\.|600\.|500\.|700\.|999\.|100\.(?:15|25|35|36|38|39|40)|300\.100\.100|000\.400\.(?:030|1[0-9][1-9]|200))/';

    /**
     * Open sessions and delayed finalization. These are valid notifications but not settlements.
     */
    private const PENDING_PATTERN = '/\A(?:000\.200\.|800\.400\.5|100\.400\.500)/';

    public static function classify(?string $code): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return self::PENDING;
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

    /** A checkout is created by 000.200.100; some test modes return a regular success code. */
    public static function isCheckoutCreated(?string $code): bool
    {
        $code = trim((string) $code);

        return $code === '000.200.100'
            || $code === '000.200.101'
            || self::classify($code) === self::PAID;
    }
}

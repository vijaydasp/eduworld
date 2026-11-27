<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/** Convert three-decimal BHD values without floating-point arithmetic. */
final class BhdAmount
{
    public static function minorUnits(string $amount): int
    {
        if (! preg_match('/\A\d{1,9}(?:\.\d{1,3})?\z/', $amount)) {
            throw new InvalidArgumentException('The amount must have at most three decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    public static function normalize(string $amount): string
    {
        $minor = self::minorUnits($amount);

        return intdiv($minor, 1000).'.'.str_pad((string) ($minor % 1000), 3, '0', STR_PAD_LEFT);
    }

    /** Format an exact two-decimal amount for AFS without changing its value. */
    public static function gatewayAmount(string $amount): string
    {
        $minorUnits = self::minorUnits($amount);

        if ($minorUnits % 10 !== 0) {
            throw new InvalidArgumentException('AFS accepts this merchant’s BHD amounts to two decimal places only; no rounding was applied.');
        }

        $hundredths = intdiv($minorUnits, 10);

        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }
}

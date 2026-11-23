<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

final class FutureKolkataDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Enter a valid future date and time in Asia/Kolkata.');

            return;
        }

        $timezone = new DateTimeZone('Asia/Kolkata');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date <= new DateTimeImmutable('now', $timezone)) {
            $fail('Enter a valid future date and time in Asia/Kolkata.');
        }
    }
}

<?php

namespace App\Sms;

use App\Models\Setting;

/** Normalises phone numbers to E.164 (+254712345678) using a default country for local formats. */
class PhoneNumber
{
    public static function normalize(?string $raw, ?string $country = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw);
        [$code, $length] = config('calling_codes.'.strtoupper($country ?: static::defaultCountry())) ?? ['254', 9];

        $e164 = match (true) {
            str_starts_with($raw, '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '0') && strlen($digits) === $length + 1 => $code.substr($digits, 1),
            strlen($digits) === $length => $code.$digits,
            default => $digits, // assume it already includes the country code
        };

        return preg_match('/^[1-9]\d{7,14}$/', $e164) ? '+'.$e164 : null;
    }

    public static function defaultCountry(): string
    {
        return Setting::get('sms_default_country', 'KE');
    }

    /** Local format for providers that want it: +254712345678 → 0712345678. */
    public static function local(string $e164, ?string $country = null): string
    {
        [$code] = config('calling_codes.'.strtoupper($country ?: static::defaultCountry())) ?? ['254'];

        return str_starts_with($e164, '+'.$code) ? '0'.substr($e164, strlen($code) + 1) : ltrim($e164, '+');
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/** Bot protection for signup forms: Google reCAPTCHA v2 (checkbox) or Cloudflare Turnstile. */
class Captcha
{
    public const PROVIDERS = [
        'none' => 'Off',
        'recaptcha' => 'Google reCAPTCHA v2 (checkbox)',
        'turnstile' => 'Cloudflare Turnstile',
    ];

    protected const CONFIG = [
        'recaptcha' => ['script' => 'https://www.google.com/recaptcha/api.js', 'global' => 'grecaptcha', 'field' => 'g-recaptcha-response', 'verify' => 'https://www.google.com/recaptcha/api/siteverify'],
        'turnstile' => ['script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js', 'global' => 'turnstile', 'field' => 'cf-turnstile-response', 'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
    ];

    public static function provider(): string
    {
        $provider = Setting::get('captcha_provider', 'none');

        return isset(self::CONFIG[$provider]) && filled(Setting::get('captcha_site_key')) && filled(Setting::get('captcha_secret')) ? $provider : 'none';
    }

    public static function enabled(): bool
    {
        return static::provider() !== 'none';
    }

    /** Front-end settings for the widget loader. */
    public static function clientConfig(): ?array
    {
        $provider = static::provider();

        return $provider === 'none' ? null : ['provider' => $provider, 'sitekey' => Setting::get('captcha_site_key'), 'script' => self::CONFIG[$provider]['script'], 'global' => self::CONFIG[$provider]['global']];
    }

    public static function passes(?string $token, ?string $ip = null): bool
    {
        $provider = static::provider();
        if ($provider === 'none') {
            return true;
        }
        if (blank($token)) {
            return false;
        }
        try {
            return (bool) Http::asForm()->timeout(10)->post(self::CONFIG[$provider]['verify'], array_filter([
                'secret' => Crypt::decryptString(Setting::get('captcha_secret')),
                'response' => $token,
                'remoteip' => $ip,
            ]))->json('success');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function tokenField(): ?string
    {
        return self::CONFIG[static::provider()]['field'] ?? null;
    }
}

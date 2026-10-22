<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Applies Settings → System mail to Laravel's mail config. System mail (password resets,
 * team notifications) is separate from the sending servers used for campaigns.
 */
class SystemMailConfig
{
    public const DRIVERS = ['smtp' => 'SMTP', 'sendgrid' => 'SendGrid (SMTP relay)', 'ses' => 'Amazon SES (API)', 'sendmail' => 'Sendmail', 'log' => 'Log only (testing)'];

    public static function apply(): void
    {
        $driver = Setting::get('mail_driver');
        if (! $driver) {
            return;
        }

        $secret = fn (string $key) => ($v = Setting::get($key)) ? Crypt::decryptString($v) : null;

        match ($driver) {
            'smtp' => config(['mail.default' => 'smtp', 'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                'transport' => 'smtp', 'host' => Setting::get('mail_host'), 'port' => (int) Setting::get('mail_port', '587'),
                'encryption' => Setting::get('mail_encryption') ?: null, 'username' => Setting::get('mail_username'), 'password' => $secret('mail_password'),
            ])]),
            'sendgrid' => config(['mail.default' => 'smtp', 'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                'transport' => 'smtp', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'apikey', 'password' => $secret('mail_password'),
            ])]),
            'ses' => config(['mail.default' => 'ses', 'services.ses' => [
                'key' => Setting::get('mail_username'), 'secret' => $secret('mail_password'), 'region' => Setting::get('mail_region', 'us-east-1'),
            ]]),
            'sendmail' => config(['mail.default' => 'sendmail', 'mail.mailers.sendmail.path' => Setting::get('mail_sendmail_path', '/usr/sbin/sendmail -bs -i')]),
            default => config(['mail.default' => 'log']),
        };

        if ($from = Setting::get('mail_from_address')) {
            config(['mail.from.address' => $from, 'mail.from.name' => Setting::get('mail_from_name') ?: Branding::appName()]);
        }
    }
}

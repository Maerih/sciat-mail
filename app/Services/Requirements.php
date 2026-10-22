<?php

namespace App\Services;

/** Server requirements, shared by the installer and Settings → System info. */
class Requirements
{
    public const PHP_VERSION = '8.3.0';

    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'curl', 'xml', 'zip', 'gd', 'intl', 'bcmath', 'fileinfo', 'ctype', 'tokenizer'];

    /** Functions the scheduler and queue need; hosts sometimes disable them. */
    public const FUNCTIONS = ['proc_open', 'escapeshellarg'];

    public const WRITABLE = ['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'];

    /** @return array<int, array{label: string, ok: bool, required: bool, detail: string}> */
    public static function check(): array
    {
        $rows = [[
            'label' => 'PHP '.self::PHP_VERSION.' or newer',
            'ok' => version_compare(PHP_VERSION, self::PHP_VERSION, '>='),
            'required' => true,
            'detail' => 'You have '.PHP_VERSION.'. Choose a newer version in your hosting panel (e.g. cPanel → Select PHP Version).',
        ]];
        foreach (self::EXTENSIONS as $extension) {
            $rows[] = ['label' => "PHP extension: {$extension}", 'ok' => extension_loaded($extension), 'required' => true, 'detail' => 'Enable it in your hosting panel\'s PHP extensions list.'];
        }
        $rows[] = ['label' => 'PHP extension: imap or iconv (bounce mailboxes)', 'ok' => extension_loaded('iconv'), 'required' => false, 'detail' => 'Needed only for reading bounce mailboxes.'];
        foreach (self::FUNCTIONS as $function) {
            $rows[] = ['label' => "PHP function: {$function}()", 'ok' => function_exists($function), 'required' => false, 'detail' => 'Used by the scheduler from cron. If the web shows it disabled, it is often still enabled for cron (CLI) — the cron status in Settings will tell you.'];
        }
        foreach (self::WRITABLE as $path) {
            $rows[] = ['label' => "Writable: {$path}/", 'ok' => is_writable(base_path($path)), 'required' => true, 'detail' => 'Set folder permissions to 755 (or 775) in the File Manager.'];
        }
        $env = app()->environmentFilePath();
        $rows[] = ['label' => 'Writable: .env', 'ok' => is_writable($env) || (! file_exists($env) && is_writable(dirname($env))), 'required' => true, 'detail' => 'The installer saves your database settings to the .env file.'];

        return $rows;
    }

    public static function passes(): bool
    {
        return collect(static::check())->every(fn ($row) => $row['ok'] || ! $row['required']);
    }
}

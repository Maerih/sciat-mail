<?php

/*
 * Runs before the framework loads. On shared hosting, switching PHP versions in the control
 * panel often resets the enabled extensions; instead of a blank "500 error" this explains
 * exactly what to change. Keep in sync with App\Services\Requirements.
 */
$sciatProblems = [];
if (PHP_VERSION_ID < 80401) {
    $sciatProblems[] = 'PHP 8.4.1 or newer is required — this server runs PHP '.PHP_VERSION.'.';
}
foreach (['pdo_mysql', 'mbstring', 'openssl', 'curl', 'xml', 'dom', 'zip', 'gd', 'intl', 'bcmath', 'fileinfo', 'ctype', 'tokenizer', 'filter', 'session'] as $sciatExtension) {
    if (! extension_loaded($sciatExtension)) {
        $sciatProblems[] = "The PHP extension “{$sciatExtension}” is not enabled.";
    }
}
foreach (['storage', 'storage/framework', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache'] as $sciatPath) {
    $sciatFull = __DIR__.'/../'.$sciatPath;
    if (! is_dir($sciatFull) || ! is_writable($sciatFull)) {
        $sciatProblems[] = "The folder “{$sciatPath}” is missing or not writable (set permissions to 755 or 775).";
    }
}

if ($sciatProblems) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $items = implode('', array_map(fn ($p) => '<li>'.htmlspecialchars($p).'</li>', $sciatProblems));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Server setup needed</title>'
        .'<body style="font-family:system-ui,sans-serif;background:#090c0a;color:#f0f0f3;padding:40px 16px"><div style="max-width:640px;margin:auto;background:#161616;border:1px solid #2e3338;border-radius:12px;padding:28px">'
        .'<h1 style="margin:0 0 8px;font-size:22px">Server setup needed</h1><p style="color:#8b8b93;margin:0 0 16px">The platform cannot start until these are fixed in your hosting control panel (cPanel → Select PHP Version → Extensions):</p>'
        ."<ul style=\"line-height:1.8;color:#fca5a5\">{$items}</ul>"
        .'<p style="color:#8b8b93;margin:16px 0 0;font-size:14px">Tip: extensions are saved per PHP version — after changing the version, tick them again. Recommended version: PHP 8.4.</p></div></body>';
    exit;
}

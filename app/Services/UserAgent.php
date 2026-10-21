<?php

namespace App\Services;

/**
 * Classifies the user agent of an open or click into device type, operating system and
 * email client. Mail provider image proxies (Gmail, Yahoo, Apple Mail Privacy Protection)
 * hide the reader's device, so those report the client with an unknown device.
 */
class UserAgent
{
    /** @return array{device: ?string, os: ?string, client: ?string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return ['device' => null, 'os' => null, 'client' => null];
        }

        // Image proxies: the provider fetched the pixel, not the reader's device
        foreach (['GoogleImageProxy' => 'Gmail', 'YahooMailProxy' => 'Yahoo Mail', 'ggpht.com' => 'Gmail'] as $needle => $client) {
            if (stripos($ua, $needle) !== false) {
                return ['device' => 'proxy', 'os' => null, 'client' => $client];
            }
        }

        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => null,
        };

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet|Kindle|Silk|(Android(?!.*Mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua) => 'mobile',
            $os !== null => 'desktop',
            default => null,
        };

        $client = match (true) {
            (bool) preg_match('/Outlook|Microsoft Office|ms-office|MSOffice/i', $ua) => 'Outlook',
            (bool) preg_match('/Thunderbird/i', $ua) => 'Thunderbird',
            (bool) preg_match('/YahooMobile|Yahoo/i', $ua) => 'Yahoo Mail',
            (bool) preg_match('/GSA\/|Gmail/i', $ua) => 'Gmail',
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Firefox\//i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS/i', $ua) => 'Chrome',
            // Apple Mail renders with WebKit and omits the Safari/Version tokens
            (bool) preg_match('/AppleWebKit/i', $ua) && ! preg_match('/Safari|Version\//i', $ua) && in_array($os, ['iOS', 'macOS'], true) => 'Apple Mail',
            (bool) preg_match('/Safari\//i', $ua) => 'Safari',
            default => null,
        };

        return ['device' => $device, 'os' => $os, 'client' => $client];
    }
}

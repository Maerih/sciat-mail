<?php

namespace Tests\Unit;

use App\Services\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function test_common_email_clients_devices_and_proxies_are_recognised(): void
    {
        $cases = [
            'Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)' => ['proxy', null, 'Gmail'],
            'YahooMailProxy; https://help.yahoo.com/kb/yahoo-mail-proxy-SLN28749.html' => ['proxy', null, 'Yahoo Mail'],
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148' => ['mobile', 'iOS', 'Apple Mail'],
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko)' => ['desktop', 'macOS', 'Apple Mail'],
            'Mozilla/4.0 (compatible; ms-office; MSOffice 16) Windows NT 10.0' => ['desktop', 'Windows', 'Outlook'],
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36' => ['mobile', 'Android', 'Chrome'],
            'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' => ['tablet', 'iOS', 'Safari'],
            'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Thunderbird/128.0' => ['desktop', 'Linux', 'Thunderbird'],
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0 Safari/537.36 Edg/129.0' => ['desktop', 'Windows', 'Edge'],
        ];

        foreach ($cases as $ua => [$device, $os, $client]) {
            $this->assertSame(['device' => $device, 'os' => $os, 'client' => $client], UserAgent::parse($ua), $ua);
        }
        $this->assertSame(['device' => null, 'os' => null, 'client' => null], UserAgent::parse(null));
    }
}

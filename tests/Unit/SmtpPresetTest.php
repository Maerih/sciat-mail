<?php

namespace Tests\Unit;

use App\Models\SendingServer;
use App\Sending\TransportFactory;
use Tests\TestCase;

class SmtpPresetTest extends TestCase
{
    public function test_preset_relays_build_the_right_smtp_dsn(): void
    {
        $factory = new TransportFactory;
        $dsn = fn (string $type, array $config) => $factory->dsn(new SendingServer(['type' => $type, 'config' => $config]));

        $postmark = $dsn('postmark', ['server_token' => 'pm-token']);
        $this->assertSame(['smtp.postmarkapp.com', 587, 'pm-token', 'pm-token'], [$postmark->getHost(), $postmark->getPort(), $postmark->getUser(), $postmark->getPassword()]);

        $sparkpost = $dsn('sparkpost', ['api_key' => 'sp', 'region' => 'eu']);
        $this->assertSame(['smtp.eu.sparkpostmail.com', 'SMTP_Injection', 'sp'], [$sparkpost->getHost(), $sparkpost->getUser(), $sparkpost->getPassword()]);

        $zepto = $dsn('zeptomail', ['token' => 'zt']);
        $this->assertSame('emailapikey', $zepto->getUser());

        $this->assertSame('spf.mtasv.net', (new SendingServer(['type' => 'postmark']))->spfInclude());
        $this->assertTrue((new SendingServer(['type' => 'brevo']))->usesSmtp());
    }
}

<?php

namespace Tests\Unit;

use App\Services\Bounces\BounceParser;
use PHPUnit\Framework\TestCase;

class BounceParserTest extends TestCase
{
    public const UID = 'abcdefghijklmnopqrstuvwx';

    protected function dsn(string $status, string $action = 'failed', string $diagnostic = 'smtp; 550 5.1.1 <ann@example.com>: Recipient address rejected: User unknown'): string
    {
        return "From: MAILER-DAEMON@mx.example.net\r\nSubject: Undelivered Mail Returned to Sender\r\nMessage-ID: <bounce-1@mx.example.net>\r\n"
            ."Content-Type: multipart/report; report-type=delivery-status; boundary=\"B\"\r\n\r\n"
            ."--B\r\nContent-Type: text/plain\r\n\r\nThis is the mail system. Your message could not be delivered.\r\n"
            ."--B\r\nContent-Type: message/delivery-status\r\n\r\nReporting-MTA: dns; mx.example.net\r\n\r\n"
            ."Final-Recipient: rfc822; ann@example.com\r\nOriginal-Recipient: rfc822;ann@example.com\r\nAction: {$action}\r\nStatus: {$status}\r\nDiagnostic-Code: {$diagnostic}\r\n"
            ."--B\r\nContent-Type: text/rfc822-headers\r\n\r\nFrom: news@client.com\r\nTo: ann@example.com\r\nMessage-ID: <orig-123@client.com>\r\nX-SM-Delivery: ".self::UID."\r\n--B--\r\n";
    }

    public function test_permanent_dsn_is_a_hard_bounce_with_delivery_and_recipient(): void
    {
        $report = BounceParser::parse($this->dsn('5.1.1'));

        $this->assertSame('bounce', $report['type']);
        $this->assertTrue($report['hard']);
        $this->assertSame(self::UID, $report['delivery_uid']);
        $this->assertSame('<orig-123@client.com>', $report['message_id']);
        $this->assertSame('ann@example.com', $report['recipient']);
        $this->assertStringContainsString('5.1.1', $report['detail']);
    }

    public function test_temporary_failures_and_full_mailboxes_are_soft(): void
    {
        $this->assertFalse(BounceParser::parse($this->dsn('4.4.1', 'failed', 'smtp; 421 Try again later'))['hard']);
        $this->assertFalse(BounceParser::parse($this->dsn('5.2.2', 'failed', 'smtp; 552 Mailbox full'))['hard']);
    }

    public function test_delay_warnings_are_not_bounces(): void
    {
        $this->assertNull(BounceParser::parse($this->dsn('4.4.7', 'delayed', 'smtp; still trying')));
    }

    public function test_classic_non_dsn_bounce_is_recognised_from_the_smtp_code(): void
    {
        $raw = "From: MAILER-DAEMON@old.example.org\r\nSubject: failure notice\r\n\r\nHi. This is the qmail-send program.\r\n"
            ."<bob@example.org>:\r\n550 5.7.1 Mailbox unavailable\r\n\r\n--- Below this line is a copy of the message.\r\nX-SM-Delivery: ".self::UID."\r\n";

        $report = BounceParser::parse($raw);

        $this->assertSame('bounce', $report['type']);
        $this->assertTrue($report['hard']);
        $this->assertSame(self::UID, $report['delivery_uid']);
    }

    public function test_arf_feedback_report_is_a_complaint(): void
    {
        $raw = "From: feedback@fbl.mailprovider.com\r\nSubject: complaint about message\r\n"
            ."Content-Type: multipart/report; report-type=feedback-report; boundary=\"F\"\r\n\r\n"
            ."--F\r\nContent-Type: message/feedback-report\r\n\r\nFeedback-Type: abuse\r\nUser-Agent: FBL/1.0\r\nVersion: 1\r\nOriginal-Rcpt-To: carl@example.com\r\n"
            ."--F\r\nContent-Type: message/rfc822\r\n\r\nFrom: news@client.com\r\nX-SM-Delivery: ".self::UID."\r\n\r\nHello\r\n--F--\r\n";

        $report = BounceParser::parse($raw);

        $this->assertSame('complaint', $report['type']);
        $this->assertSame(self::UID, $report['delivery_uid']);
        $this->assertSame('carl@example.com', $report['recipient']);
    }

    public function test_auto_replies_and_ordinary_mail_are_ignored(): void
    {
        $this->assertNull(BounceParser::parse("From: ann@example.com\r\nSubject: Out of office\r\nAuto-Submitted: auto-replied\r\n\r\nI am away until Monday.\r\n"));
        $this->assertNull(BounceParser::parse("From: client@example.com\r\nSubject: Question about my order\r\n\r\nHello, can you help?\r\n"));
    }
}

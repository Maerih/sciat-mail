<?php

namespace Tests\Feature;

use App\Http\Controllers\Public\WebhookController;
use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Sending\TrackingUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected Delivery $delivery;

    protected Subscriber $subscriber;

    protected TrackingUrls $urls;

    protected function setUp(): void
    {
        parent::setUp();
        $list = MailList::factory()->create();
        $this->subscriber = Subscriber::factory()->for($list, 'list')->create(['email' => 'ann@example.com']);
        $campaign = Campaign::factory()->for($list->workspace)->create(['status' => 'done']);
        $this->delivery = Delivery::create([
            'campaign_id' => $campaign->id, 'subscriber_id' => $this->subscriber->id, 'workspace_id' => $list->workspace_id,
            'email' => 'ann@example.com', 'status' => 'sent',
        ]);
        $this->delivery->forceFill(['provider_message_id' => 'provider-123'])->save();
        $this->urls = app(TrackingUrls::class);
    }

    public function test_open_pixel_records_open_and_returns_gif(): void
    {
        $this->get('/t/o/'.$this->delivery->uid.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get('/t/o/'.$this->delivery->uid.'.gif');

        $this->delivery->refresh();
        $this->assertNotNull($this->delivery->opened_at);
        $this->assertSame(2, $this->delivery->open_count);
        $this->assertNotNull($this->subscriber->fresh()->last_open_at);
    }

    public function test_signed_click_redirects_and_counts_as_open(): void
    {
        $url = 'https://shop.example.com/sale?a=1';

        $this->get($this->urls->click($this->delivery, $url))->assertRedirect($url);

        $this->delivery->refresh();
        $this->assertSame(1, $this->delivery->click_count);
        $this->assertNotNull($this->delivery->opened_at);
        $this->assertDatabaseHas('delivery_events', ['delivery_id' => $this->delivery->id, 'type' => 'click', 'url' => $url]);
    }

    public function test_click_with_tampered_url_is_rejected(): void
    {
        $signed = $this->urls->click($this->delivery, 'https://shop.example.com');
        $tampered = preg_replace('/\?u=.*/', '?u='.$this->urls->b64('https://evil.example'), $signed);

        $this->get($tampered)->assertNotFound();
        $this->assertSame(0, $this->delivery->fresh()->click_count);
    }

    public function test_unsubscribe_link_requires_confirmation_before_unsubscribing(): void
    {
        $url = $this->urls->unsubscribe($this->delivery);

        $this->get($url)->assertOk()->assertSee('Unsubscribe me');
        $this->assertSame('subscribed', $this->subscriber->fresh()->status, 'link scanners must not unsubscribe people');

        $this->post($url)->assertOk()->assertSee('You have been unsubscribed');
        $this->assertSame('unsubscribed', $this->subscriber->fresh()->status);
    }

    public function test_one_click_unsubscribe_post_from_mail_client(): void
    {
        $this->post($this->urls->unsubscribe($this->delivery), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()->assertSeeText('Unsubscribed');

        $this->assertSame('unsubscribed', $this->subscriber->fresh()->status);
        $this->assertDatabaseHas('delivery_events', ['delivery_id' => $this->delivery->id, 'type' => 'unsubscribe', 'subtype' => 'one-click']);
    }

    public function test_unsubscribe_with_bad_signature_is_rejected(): void
    {
        $this->post('/u/'.$this->delivery->uid.'/0000000000')->assertNotFound();
        $this->assertSame('subscribed', $this->subscriber->fresh()->status);
    }

    public function test_ses_hard_bounce_blacklists_address(): void
    {
        app(WebhookController::class)->handleSesNotification([
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'provider-123'],
            'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'ann@example.com', 'diagnosticCode' => '550 5.1.1 user unknown']]],
        ]);

        $this->assertSame('bounced', $this->delivery->fresh()->status);
        $this->assertTrue(Blacklist::isBlocked('ann@example.com', $this->delivery->workspace_id));
        $this->assertSame('blacklisted', $this->subscriber->fresh()->status);
    }

    public function test_ses_soft_bounce_is_logged_without_suppression(): void
    {
        app(WebhookController::class)->handleSesNotification([
            'notificationType' => 'Bounce',
            'mail' => ['tags' => ['delivery' => [$this->delivery->uid]]],
            'bounce' => ['bounceType' => 'Transient', 'bouncedRecipients' => [['emailAddress' => 'ann@example.com']]],
        ]);

        $this->assertDatabaseHas('delivery_events', ['delivery_id' => $this->delivery->id, 'type' => 'bounce', 'subtype' => 'soft']);
        $this->assertFalse(Blacklist::isBlocked('ann@example.com', $this->delivery->workspace_id));
    }

    public function test_ses_endpoint_rejects_unsigned_messages(): void
    {
        $server = SendingServer::factory()->ofType('ses-api', ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1'])->create();

        $this->postJson(route('webhooks.ses', ['server' => $server->uid, 'secret' => $server->webhook_secret]), [
            'Type' => 'Notification', 'Message' => '{}', 'MessageId' => '1', 'TopicArn' => 'arn', 'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1', 'Signature' => 'bogus', 'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ])->assertForbidden();
    }

    public function test_sendgrid_spam_report_blacklists_the_complainer(): void
    {
        $server = SendingServer::factory()->ofType('sendgrid-api', ['api_key' => 'x'])->create();

        $this->postJson(route('webhooks.sendgrid', ['server' => $server->uid, 'secret' => $server->webhook_secret]), [
            ['event' => 'spamreport', 'email' => 'ann@example.com', 'delivery' => $this->delivery->uid],
        ])->assertOk();

        $this->assertSame('complained', $this->delivery->fresh()->status);
        $this->assertSame('blacklisted', $this->subscriber->fresh()->status);
        $this->assertTrue(Blacklist::isBlocked('ann@example.com', $this->delivery->workspace_id));
    }

    public function test_webhook_with_wrong_secret_is_rejected(): void
    {
        $server = SendingServer::factory()->ofType('sendgrid-api', ['api_key' => 'x'])->create();

        $this->postJson(route('webhooks.sendgrid', ['server' => $server->uid, 'secret' => 'wrong']), [
            ['event' => 'spamreport', 'delivery' => $this->delivery->uid],
        ])->assertNotFound();

        $this->assertSame('sent', $this->delivery->fresh()->status);
    }

    public function test_mailgun_permanent_failure_with_valid_signature_is_a_hard_bounce(): void
    {
        $server = SendingServer::factory()->ofType('mailgun-api', ['api_key' => 'x', 'domain' => 'mg.example.com', 'region' => 'us', 'webhook_signing_key' => 'signing-key'])->create();
        $timestamp = (string) time();
        $token = 'abc123';

        $this->postJson(route('webhooks.mailgun', ['server' => $server->uid, 'secret' => $server->webhook_secret]), [
            'signature' => ['timestamp' => $timestamp, 'token' => $token, 'signature' => hash_hmac('sha256', $timestamp.$token, 'signing-key')],
            'event-data' => ['event' => 'failed', 'severity' => 'permanent', 'user-variables' => ['delivery' => $this->delivery->uid]],
        ])->assertOk();

        $this->assertSame('bounced', $this->delivery->fresh()->status);
    }

    public function test_mailgun_webhook_with_invalid_signature_is_rejected(): void
    {
        $server = SendingServer::factory()->ofType('mailgun-api', ['api_key' => 'x', 'domain' => 'mg.example.com', 'region' => 'us', 'webhook_signing_key' => 'signing-key'])->create();

        $this->postJson(route('webhooks.mailgun', ['server' => $server->uid, 'secret' => $server->webhook_secret]), [
            'signature' => ['timestamp' => '1', 'token' => 't', 'signature' => 'forged'],
            'event-data' => ['event' => 'failed', 'severity' => 'permanent', 'user-variables' => ['delivery' => $this->delivery->uid]],
        ])->assertForbidden();

        $this->assertSame('sent', $this->delivery->fresh()->status);
    }
}

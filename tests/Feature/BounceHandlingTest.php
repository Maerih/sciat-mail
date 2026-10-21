<?php

namespace Tests\Feature;

use App\Models\Blacklist;
use App\Models\BounceHandler;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Bounces\BounceProcessor;
use App\Services\Bounces\Mailbox;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BounceHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
    }

    /** Installs a mailbox fake holding the given raw messages. */
    protected function mailbox(array $messages): object
    {
        $fake = new class($messages) implements Mailbox
        {
            public array $processed = [];

            public function __construct(public array $messages) {}

            public function unread(BounceHandler $handler, int $limit): iterable
            {
                foreach ($this->messages as $id => $raw) {
                    yield ['id' => $id, 'raw' => $raw];
                }
            }

            public function markProcessed(BounceHandler $handler, mixed $id, bool $delete): void
            {
                $this->processed[$id] = $delete ? 'deleted' : 'seen';
            }

            public function close(): void {}
        };
        $this->app->instance(Mailbox::class, $fake);

        return $fake;
    }

    protected function handler(array $attributes = []): BounceHandler
    {
        return BounceHandler::create($attributes + [
            'workspace_id' => $this->workspace->id, 'name' => 'Bounces', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'bounces@client.com', 'password' => 'secret',
        ]);
    }

    protected function delivery(string $email = 'ann@example.com'): Delivery
    {
        $list = MailList::factory()->for($this->workspace)->create();
        $subscriber = Subscriber::factory()->for($list, 'list')->create(['email' => $email]);
        $campaign = Campaign::factory()->for($this->workspace)->create(['status' => 'done']);
        $delivery = Delivery::create(['campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id, 'workspace_id' => $this->workspace->id,
            'email' => $email, 'status' => 'sent']);
        $delivery->forceFill(['sent_at' => now()])->save();

        return $delivery;
    }

    public function test_hard_bounce_from_mailbox_blacklists_and_marks_message_seen(): void
    {
        $delivery = $this->delivery();
        $raw = "From: MAILER-DAEMON@mx.test\r\nSubject: Undelivered Mail Returned to Sender\r\nContent-Type: multipart/report; report-type=delivery-status; boundary=B\r\n\r\n"
            ."--B\r\nContent-Type: message/delivery-status\r\n\r\nFinal-Recipient: rfc822; ann@example.com\r\nAction: failed\r\nStatus: 5.1.1\r\n"
            ."--B\r\nContent-Type: text/rfc822-headers\r\n\r\nX-SM-Delivery: {$delivery->uid}\r\n--B--\r\n";
        $mailbox = $this->mailbox(['m1' => $raw, 'm2' => "From: someone@x.com\r\nSubject: Hello\r\n\r\nNot a bounce\r\n"]);
        $handler = $this->handler();

        $stats = app(BounceProcessor::class)->process($handler);

        $this->assertSame(['read' => 2, 'bounces' => 1, 'complaints' => 0, 'unmatched' => 0], $stats);
        $this->assertSame('bounced', $delivery->fresh()->status);
        $this->assertTrue(Blacklist::isBlocked('ann@example.com', $this->workspace->id));
        $this->assertSame(['m1' => 'seen'], $mailbox->processed, 'ordinary mail is left unread');
        $this->assertSame(1, $handler->fresh()->processed_count);
        $this->assertNotNull($handler->fresh()->last_checked_at);
    }

    public function test_bounce_without_tracking_header_falls_back_to_recipient_address(): void
    {
        $delivery = $this->delivery('bob@example.org');
        $this->mailbox(['m1' => "From: MAILER-DAEMON@old.test\r\nSubject: failure notice\r\n\r\n<bob@example.org>:\r\nFinal-Recipient: rfc822; bob@example.org\r\n550 5.7.1 rejected\r\n"]);

        app(BounceProcessor::class)->process($this->handler(['delete_processed' => true]));

        $this->assertSame('bounced', $delivery->fresh()->status);
    }

    public function test_bounces_for_another_workspace_are_ignored(): void
    {
        $delivery = $this->delivery();
        $raw = "From: MAILER-DAEMON@mx.test\r\nSubject: Undelivered Mail\r\nContent-Type: multipart/report; report-type=delivery-status\r\n\r\nAction: failed\r\nStatus: 5.1.1\r\nX-SM-Delivery: {$delivery->uid}\r\n";
        $this->mailbox(['m1' => $raw]);

        $stats = app(BounceProcessor::class)->process($this->handler(['workspace_id' => Workspace::factory()->create()->id]));

        $this->assertSame(1, $stats['unmatched']);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_connection_errors_are_recorded_on_the_handler(): void
    {
        $this->app->instance(Mailbox::class, new class implements Mailbox
        {
            public function unread(BounceHandler $handler, int $limit): iterable
            {
                throw new \RuntimeException('Connection refused');
            }

            public function markProcessed(BounceHandler $handler, mixed $id, bool $delete): void {}

            public function close(): void {}
        });
        $handler = $this->handler();
        $this->signInTo($this->workspace);

        $this->post(route('sending.bounce-handlers.check', $handler))->assertSessionHas('error');

        $this->assertSame('Connection refused', $handler->fresh()->last_error);
    }

    public function test_smtp_server_bounce_address_becomes_the_envelope_sender(): void
    {
        SendingServer::factory()->for($this->workspace)->create(['bounce_address' => 'bounces@client.com']);
        $list = MailList::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($list, 'list')->create();
        $campaign = Campaign::factory()->for($this->workspace)->create(['from_email' => 'news@client.com']);
        $campaign->audiences()->create(['mail_list_id' => $list->id]);

        app(CampaignService::class)->launch($campaign);

        $sent = $this->transport->sent[0];
        $this->assertSame('bounces@client.com', $sent->getEnvelope()->getSender()->getAddress());
        $this->assertSame('news@client.com', $sent->getOriginalMessage()->getFrom()[0]->getAddress());
    }

    public function test_handler_passwords_are_encrypted_and_never_shown(): void
    {
        $this->signInTo($this->workspace);

        $this->post(route('sending.bounce-handlers.store'), ['name' => 'B', 'host' => 'imap.test', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'u@test', 'password' => 'imap-secret-pw'])->assertRedirect();

        $this->assertStringNotContainsString('imap-secret-pw', BounceHandler::first()->getRawOriginal('password'));
        $this->get(route('sending.bounce-handlers.index'))->assertOk()->assertDontSee('imap-secret-pw');
    }
}

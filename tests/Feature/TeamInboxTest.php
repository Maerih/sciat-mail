<?php

namespace Tests\Feature;

use App\Models\InboxMailbox;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Inbox\InboxMailer;
use App\Services\Inbox\InboxSync;
use App\Services\Inbox\MailboxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeamInboxTest extends TestCase
{
    use RefreshDatabase;

    protected InboxMailbox $mailbox;

    protected FakeMailboxClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailbox = InboxMailbox::create(['name' => 'Info', 'email' => 'info@sciatafrica.test', 'imap_host' => 'mail.sciatafrica.test', 'smtp_host' => 'mail.sciatafrica.test',
            'username' => 'info@sciatafrica.test', 'password' => 'secret', 'signature' => '<p>— SCIAT Africa</p>']);
        $this->client = new FakeMailboxClient;
        $this->app->instance(MailboxClient::class, $this->client);
        $this->app->instance(InboxMailer::class, new InboxMailer($this->client, $this->transport));
    }

    protected function incoming(int $uid, array $overrides = []): array
    {
        return $overrides + [
            'uid' => $uid, 'message_id' => "m{$uid}@client.test", 'in_reply_to' => '', 'references' => '', 'subject' => 'Need a penetration test',
            'from_email' => 'jane@client.test', 'from_name' => 'Jane Doe', 'to' => [['email' => 'info@sciatafrica.test', 'name' => '']], 'cc' => [],
            'date' => Carbon::now()->subMinutes(10 - $uid), 'text' => "Hello, can you quote a pentest? #{$uid}", 'html' => null, 'seen' => false, 'attachments' => [],
        ];
    }

    public function test_sync_groups_messages_into_conversations(): void
    {
        $this->client->messages['INBOX'] = [
            $this->incoming(1, ['attachments' => [['name' => 'scope.pdf', 'mime' => 'application/pdf', 'content' => '%PDF-1.4']]]),
            $this->incoming(2, ['subject' => 'Re: Need a penetration test', 'in_reply_to' => '<m1@client.test>', 'message_id' => 'm2@client.test']),
            $this->incoming(3, ['subject' => 'Training dates?', 'from_email' => 'ken@other.test', 'from_name' => 'Ken']),
        ];

        $this->assertSame(3, app(InboxSync::class)->sync($this->mailbox));
        $this->assertSame(0, app(InboxSync::class)->sync($this->mailbox), 'no duplicates on the next run');

        $this->assertSame(2, InboxThread::count());
        $thread = InboxThread::where('contact_email', 'jane@client.test')->sole();
        $this->assertSame(2, $thread->message_count);
        $this->assertSame(2, $thread->unread);
        $this->assertSame('scope.pdf', InboxMessage::where('imap_uid', 1)->first()->attachments[0]['name']);
        $this->assertNotNull($this->mailbox->fresh()->last_synced_at);
    }

    public function test_team_reads_and_replies_with_threading_headers(): void
    {
        $this->client->messages['INBOX'] = [$this->incoming(1)];
        app(InboxSync::class)->sync($this->mailbox);
        $thread = InboxThread::sole();
        $this->signInTo(Workspace::factory()->create());

        $this->get(route('inbox.index'))->assertOk()->assertSee('Need a penetration test')->assertSee('Jane Doe');
        $this->get(route('inbox.index', ['thread' => $thread->uid]))->assertOk()->assertSee('can you quote a pentest', false);
        $this->assertSame(0, $thread->fresh()->unread, 'opening marks it read');

        $this->post(route('inbox.reply', $thread), ['to' => 'jane@client.test', 'body' => "Hi Jane,\nHappy to help.", 'files' => [UploadedFile::fake()->create('quote.pdf', 20, 'application/pdf')]])
            ->assertRedirect();

        $sent = $this->transport->sent[0]->toString();
        $this->assertStringContainsString('In-Reply-To: <m1@client.test>', $sent);
        $this->assertStringContainsString('Subject: Re: Need a penetration test', $sent);
        $this->assertStringContainsString('SCIAT Africa', $sent, 'signature added');
        $this->assertCount(1, $this->client->appended, 'copy saved to Sent');
        $reply = InboxMessage::where('direction', 'out')->sole();
        $this->assertSame($thread->id, $reply->inbox_thread_id);
        $this->assertSame('quote.pdf', $reply->attachments[0]['name']);

        // the copy found later in the Sent folder is not duplicated
        $this->client->messages['INBOX.Sent'] = [$this->incoming(9, ['message_id' => $reply->message_id, 'from_email' => 'info@sciatafrica.test', 'to' => [['email' => 'jane@client.test', 'name' => '']]])];
        $this->assertSame(0, app(InboxSync::class)->sync($this->mailbox));
    }

    public function test_compose_starts_a_new_conversation(): void
    {
        $this->signInTo(Workspace::factory()->create());

        $this->post(route('inbox.compose'), ['mailbox' => $this->mailbox->uid, 'to' => 'new@lead.test', 'subject' => 'Your free security assessment', 'body' => 'Hello!'])->assertRedirect();

        $thread = InboxThread::sole();
        $this->assertSame('new@lead.test', $thread->contact_email);
        $this->assertStringContainsString('From: '.$this->mailbox->name.' <info@sciatafrica.test>', $this->transport->sent[0]->toString());
    }

    public function test_thread_actions_and_mailbox_visibility(): void
    {
        $this->client->messages['INBOX'] = [$this->incoming(1)];
        app(InboxSync::class)->sync($this->mailbox);
        $thread = InboxThread::sole();
        $workspace = Workspace::factory()->create();
        $member = $this->signInTo($workspace);

        $this->patch(route('inbox.update', $thread), ['action' => 'star']);
        $this->patch(route('inbox.update', $thread), ['action' => 'assign', 'user_id' => $member->id]);
        $this->patch(route('inbox.update', $thread), ['action' => 'done']);
        $thread->refresh();
        $this->assertTrue($thread->starred);
        $this->assertSame($member->id, $thread->assigned_user_id);
        $this->assertSame('done', $thread->status);

        // a new message reopens it
        $this->client->messages['INBOX'][] = $this->incoming(2, ['in_reply_to' => '<m1@client.test>', 'message_id' => 'm2@client.test']);
        app(InboxSync::class)->sync($this->mailbox);
        $this->assertSame('open', $thread->fresh()->status);

        // restricting the mailbox to someone else hides it from this member
        $this->mailbox->members()->sync([User::factory()->create()->id]);
        $this->get(route('inbox.index', ['thread' => $thread->uid]))->assertOk()->assertDontSee('Need a penetration test');
        $this->patch(route('inbox.update', $thread), ['action' => 'star'])->assertNotFound();
    }

    public function test_admin_manages_mailboxes_and_password_is_encrypted(): void
    {
        $this->signInTo(Workspace::factory()->create(), 'admin');
        $this->get(route('inbox.mailboxes.index'))->assertOk();
        $this->post(route('inbox.mailboxes.store'), ['name' => 'Maeri', 'email' => 'maeri@sciatafrica.test', 'imap_host' => 'mail.sciatafrica.test', 'imap_port' => 993, 'imap_encryption' => 'ssl',
            'smtp_host' => 'mail.sciatafrica.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'username' => 'maeri@sciatafrica.test', 'password' => 'p@ss', 'status' => 'active'])->assertRedirect();

        $mailbox = InboxMailbox::where('email', 'maeri@sciatafrica.test')->sole();
        $this->assertNotSame('p@ss', \DB::table('inbox_mailboxes')->where('id', $mailbox->id)->value('password'));
        $this->post(route('inbox.mailboxes.test', $mailbox))->assertSessionHas('error'); // SMTP host does not exist in tests

        $this->signInTo(Workspace::factory()->create());
        $this->get(route('inbox.mailboxes.index'))->assertForbidden();
    }
}

class FakeMailboxClient implements MailboxClient
{
    public array $messages = ['INBOX' => [], 'INBOX.Sent' => []];

    public array $appended = [];

    public function folders(InboxMailbox $mailbox): array
    {
        return ['inbox' => 'INBOX', 'sent' => 'INBOX.Sent'];
    }

    public function fetch(InboxMailbox $mailbox, string $folder, Carbon $since, int $limit): iterable
    {
        return $this->messages[$folder] ?? [];
    }

    public function markSeen(InboxMailbox $mailbox, string $folder, int $uid, bool $seen): void {}

    public function appendToSent(InboxMailbox $mailbox, string $rawMessage): void
    {
        $this->appended[] = $rawMessage;
    }
}

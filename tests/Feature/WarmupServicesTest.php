<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingDomain;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\WarmupEmail;
use App\Models\WarmupMailbox;
use App\Models\WarmupStrategy;
use App\Models\Workspace;
use App\Services\CampaignService;
use App\Services\Warmup\EmailWarmup;
use App\Services\Warmup\WarmupInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarmupServicesTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC']);
    }

    public function test_prewarmed_servers_skip_the_warmup_limit(): void
    {
        $strategy = WarmupStrategy::create(['name' => 'Tiny', 'curve' => 'linear', 'initial_daily' => 3, 'target_daily' => 30, 'days' => 10]);
        $server = SendingServer::factory()->for($this->workspace)->create(['warmup_strategy_id' => $strategy->id, 'warmup_started_on' => now()->toDateString()]);
        $this->assertSame(3, $server->effectiveDailyLimit());

        $server->update(['prewarmed' => true]);
        $this->assertFalse($server->fresh()->isWarmingUp());
        $this->assertNull($server->fresh()->effectiveDailyLimit());
    }

    public function test_domain_warmup_caps_sends_from_the_domain_across_servers(): void
    {
        SendingServer::factory()->for($this->workspace)->count(2)->create();
        $strategy = WarmupStrategy::create(['name' => 'Tiny', 'curve' => 'linear', 'initial_daily' => 2, 'target_daily' => 20, 'days' => 10]);
        $domain = SendingDomain::create(['workspace_id' => $this->workspace->id, 'name' => 'acme.test']);
        $domain->forceFill(['warmup_strategy_id' => $strategy->id, 'warmup_started_on' => now()->toDateString()])->save();
        $list = MailList::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($list, 'list')->count(5)->create();
        $campaign = Campaign::factory()->for($this->workspace)->create(['from_email' => 'news@acme.test']);
        $campaign->audiences()->create(['mail_list_id' => $list->id]);

        $this->assertSame(2, $domain->fresh()->dailyAllowance());
        app(CampaignService::class)->launch($campaign);

        $this->assertCount(2, $this->transport->sent);
    }

    public function test_domain_warmup_can_be_set_from_the_domain_page(): void
    {
        $strategy = WarmupStrategy::create(['name' => 'Tiny', 'curve' => 'linear', 'initial_daily' => 2, 'target_daily' => 20, 'days' => 10]);
        $domain = SendingDomain::create(['workspace_id' => $this->workspace->id, 'name' => 'acme.test']);
        $this->signInTo($this->workspace);

        $this->get(route('sending.domains.show', $domain))->assertOk()->assertSee('Domain warmup');
        $this->put(route('sending.domains.warmup', $domain), ['warmup_strategy_id' => $strategy->id])->assertSessionHas('success');

        $this->assertTrue($domain->fresh()->isWarmingUp());
    }

    public function test_email_warmup_sends_to_seed_inboxes_and_records_placement(): void
    {
        $server = SendingServer::factory()->for($this->workspace)->create(['email_warmup' => ['enabled' => true, 'from_email' => 'hello@acme.test', 'daily_max' => 40, 'started_on' => now()->toDateString()]]);
        $mailbox = new WarmupMailbox(['email' => 'seed@gmail.test', 'host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'seed', 'password' => 'p', 'status' => 'active']);
        $mailbox->workspace_id = $this->workspace->id;
        $mailbox->save();

        $this->assertSame(4, $server->emailWarmupTarget(), 'day 1 starts at 4');
        $sent = app(EmailWarmup::class)->sendFor($server, ignoreWindow: true);

        $this->assertSame(4, $sent);
        $this->assertCount(4, $this->transport->sent);
        $this->assertStringContainsString('Ref: ', $this->transport->sent[0]->toString());

        $tokens = WarmupEmail::pluck('token')->all();
        $this->app->instance(WarmupInbox::class, new class($tokens) implements WarmupInbox
        {
            public function __construct(public array $tokens) {}

            public function scan(WarmupMailbox $mailbox, array $tokens): array
            {
                return [$this->tokens[0] => 'inbox', $this->tokens[1] => 'inbox', $this->tokens[2] => 'spam'];
            }
        });
        app(EmailWarmup::class)->check();

        $stats = EmailWarmup::stats($server);
        $this->assertSame(2, $stats['inbox']);
        $this->assertSame(1, $stats['spam']);
        $this->assertTrue(WarmupEmail::where('placement', 'spam')->sole()->rescued);

        $this->travel(3)->days();
        app(EmailWarmup::class)->check();
        $this->assertSame(1, WarmupEmail::where('placement', 'missing')->count(), 'never seen in two days = missing');
        $this->assertSame(min(40, 1 + 3 * 4), $server->fresh()->emailWarmupTarget(), 'the daily target grows');
    }

    public function test_warmup_page_and_server_settings(): void
    {
        $server = SendingServer::factory()->for($this->workspace)->create();
        $this->signInTo($this->workspace);

        $this->get(route('sending.email-warmup.index'))->assertOk()->assertSee('Warm up with real engagement');
        $this->post(route('sending.email-warmup.mailboxes.store'), ['email' => 'seed@outlook.test', 'host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'seed', 'password' => 'secret'])
            ->assertSessionHas('success');
        $this->assertNotSame('secret', \DB::table('warmup_mailboxes')->value('password'), 'password is encrypted');

        $this->put(route('sending.email-warmup.servers.update', $server), ['enabled' => '1', 'from_email' => 'hello@acme.test', 'daily_max' => 30])->assertSessionHas('success');
        $this->assertTrue($server->fresh()->emailWarmup('enabled'));
        $this->assertSame(now()->toDateString(), $server->fresh()->emailWarmup('started_on'));
    }
}

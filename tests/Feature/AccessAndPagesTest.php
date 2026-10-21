<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingDomain;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\TrackingDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Sending\TrackingUrls;
use App\Services\Dns\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAndPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/campaigns')->assertRedirect(route('login'));
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_any_workspace_is_sent_to_create_one(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('workspaces.create'));
    }

    public function test_records_of_another_workspace_are_not_reachable(): void
    {
        $mine = Workspace::factory()->create();
        $theirs = Workspace::factory()->create();
        $theirList = MailList::factory()->for($theirs)->create();
        $theirCampaign = Campaign::factory()->for($theirs)->create();
        $theirServer = SendingServer::factory()->for($theirs)->create();
        $theirSubscriber = Subscriber::factory()->for($theirList, 'list')->create();
        $this->signInTo($mine);

        $this->get(route('lists.show', $theirList->uid))->assertNotFound();
        $this->get(route('lists.subscribers.index', $theirList->uid))->assertNotFound();
        $this->get(route('campaigns.show', $theirCampaign->uid))->assertNotFound();
        $this->post(route('campaigns.launch', $theirCampaign->uid))->assertNotFound();
        $this->get(route('sending.servers.edit', $theirServer->uid))->assertNotFound();
        $this->delete(route('lists.destroy', $theirList->uid))->assertNotFound();
        $this->assertModelExists($theirList);
        $this->assertModelExists($theirSubscriber);
    }

    public function test_subscriber_must_belong_to_the_list_in_the_url(): void
    {
        $workspace = Workspace::factory()->create();
        [$listA, $listB] = MailList::factory()->for($workspace)->count(2)->create();
        $subscriber = Subscriber::factory()->for($listB, 'list')->create();
        $this->signInTo($workspace);

        $this->get(route('lists.subscribers.show', [$listA->uid, $subscriber->uid]))->assertNotFound();
    }

    public function test_shared_servers_are_usable_but_only_admins_may_change_them(): void
    {
        $workspace = Workspace::factory()->create();
        $shared = SendingServer::factory()->shared()->create();
        $this->signInTo($workspace);

        $this->get(route('sending.servers.edit', $shared->uid))->assertOk();
        $this->delete(route('sending.servers.destroy', $shared->uid))->assertForbidden();

        $this->signInTo($workspace, 'admin');
        $this->delete(route('sending.servers.destroy', $shared->uid))->assertRedirect();
        $this->assertModelMissing($shared);
    }

    public function test_members_cannot_manage_team_or_list_all_clients(): void
    {
        $this->signInTo(Workspace::factory()->create());

        $this->get(route('team.index'))->assertForbidden();
        $this->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'long-password-1', 'role' => 'admin'])->assertForbidden();
        $this->get(route('workspaces.index'))->assertForbidden();
    }

    public function test_switching_workspace_changes_what_is_shown(): void
    {
        [$a, $b] = Workspace::factory()->count(2)->create();
        MailList::factory()->for($b)->create(['name' => 'Only in B']);
        $this->signInTo($a);

        $this->get(route('lists.index'))->assertDontSee('Only in B');
        $this->post(route('workspaces.switch', $b->uid))->assertRedirect(route('dashboard'));
        $this->get(route('lists.index'))->assertSee('Only in B');
    }

    public function test_server_secrets_are_kept_when_left_blank_on_edit_and_never_rendered(): void
    {
        $workspace = Workspace::factory()->create();
        $server = SendingServer::factory()->for($workspace)
            ->ofType('smtp', ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'super-secret'])
            ->create();
        $this->signInTo($workspace);

        $this->get(route('sending.servers.edit', $server->uid))->assertOk()->assertDontSee('super-secret');

        $this->put(route('sending.servers.update', $server->uid), [
            'name' => 'Renamed', 'status' => 'active',
            'config' => ['host' => 'smtp2.example.com', 'port' => 2525, 'encryption' => 'tls', 'username' => 'u', 'password' => ''],
        ])->assertRedirect();

        $server->refresh();
        $this->assertSame('super-secret', $server->configValue('password'));
        $this->assertSame('smtp2.example.com', $server->configValue('host'));
        $this->assertStringNotContainsString('super-secret', $server->getRawOriginal('config'), 'credentials are encrypted at rest');
    }

    public function test_domain_verification_checks_each_dns_record(): void
    {
        $workspace = Workspace::factory()->create();
        $domain = SendingDomain::create(['workspace_id' => $workspace->id, 'name' => 'Example.COM']);
        $this->app->instance(DnsResolver::class, new class($domain) implements DnsResolver
        {
            public function __construct(private SendingDomain $domain) {}

            public function txt(string $host): array
            {
                return match ($host) {
                    $this->domain->verificationHost() => [$this->domain->verificationValue()],
                    $this->domain->dkimHost() => [chunk_split($this->domain->dkimValue(), 200, ' ')], // providers may split long values
                    'example.com' => ['v=spf1 include:amazonses.com ~all'],
                    default => [],
                };
            }

            public function mx(string $domain): bool
            {
                return true;
            }

            public function cname(string $host): array
            {
                return $host === 'links.example.com' ? ['localhost'] : [];
            }
        });
        $this->signInTo($workspace);

        $this->post(route('sending.domains.verify', $domain->uid))->assertSessionHas('success');

        $domain->refresh();
        $this->assertSame('example.com', $domain->name);
        $this->assertTrue($domain->ownership_verified && $domain->dkim_verified && $domain->spf_verified);
        $this->assertFalse($domain->dmarc_verified);

        $tracking = TrackingDomain::create(['workspace_id' => $workspace->id, 'host' => 'links.example.com']);
        $this->post(route('sending.tracking-domains.verify', $tracking->uid));
        $this->assertTrue($tracking->fresh()->verified);
    }

    public function test_tracking_links_use_verified_tracking_domain(): void
    {
        $workspace = Workspace::factory()->create();
        $tracking = TrackingDomain::create(['workspace_id' => $workspace->id, 'host' => 'links.client.com', 'is_default' => true]);
        $tracking->forceFill(['verified' => true])->save();

        $this->assertSame('https://links.client.com', app(TrackingUrls::class)->base($workspace));
    }

    public function test_every_page_renders_for_a_populated_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $list = MailList::factory()->for($workspace)->create();
        $subscriber = Subscriber::factory()->for($list, 'list')->create();
        $segment = $list->segments()->create(['name' => 'All', 'matching' => 'all', 'conditions' => [['field' => 'EMAIL', 'operator' => 'contains', 'value' => '@']]]);
        $form = $list->forms()->create(['name' => 'Footer']);
        $server = SendingServer::factory()->for($workspace)->create();
        $domain = SendingDomain::create(['workspace_id' => $workspace->id, 'name' => 'example.com']);
        $draft = Campaign::factory()->for($workspace)->create();
        $draft->audiences()->create(['mail_list_id' => $list->id]);
        $sent = Campaign::factory()->for($workspace)->create(['status' => 'done', 'started_at' => now()->subHour()]);
        $this->signInTo($workspace, 'admin');

        $pages = [
            route('dashboard'), route('campaigns.index'), route('campaigns.create'),
            route('campaigns.recipients', $draft), route('campaigns.setup', $draft), route('campaigns.content', $draft),
            route('campaigns.schedule', $draft), route('campaigns.confirm', $draft), route('campaigns.preview', $draft),
            route('campaigns.show', $sent), route('campaigns.log', [$sent, 'deliveries']), route('campaigns.log', [$sent, 'clicks']),
            route('campaigns.chart', $sent), route('templates.index'), route('templates.create'),
            route('lists.index'), route('lists.create'), route('lists.show', $list), route('lists.edit', $list), route('lists.fields', $list),
            route('lists.subscribers.index', $list), route('lists.subscribers.create', $list), route('lists.subscribers.show', [$list, $subscriber]),
            route('lists.subscribers.edit', [$list, $subscriber]), route('lists.segments.index', $list), route('lists.segments.create', $list),
            route('lists.segments.edit', [$list, $segment]), route('lists.import.create', $list),
            route('forms.index'), route('forms.create'), route('forms.edit', $form), route('forms.embed', $form), route('subscribe.show', $form->uid),
            route('sending.servers.index'), route('sending.servers.select'), route('sending.servers.edit', $server),
            route('sending.domains.index'), route('sending.domains.show', $domain), route('sending.tracking-domains.index'),
            route('sending.blacklist.index'), route('workspaces.edit'), route('workspaces.index'), route('team.index'), route('profile.edit'),
        ];
        foreach (array_keys(SendingServer::TYPES) as $type) {
            $pages[] = route('sending.servers.create', ['type' => $type]);
        }

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }
}

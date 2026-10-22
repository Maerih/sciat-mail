<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['company' => 'Bloom Bakery', 'brand_color' => '#be185d']);
        $this->client = User::factory()->create(['role' => 'client', 'workspace_id' => $this->workspace->id, 'password' => 'portal-password-1']);
    }

    public function test_client_login_lands_in_their_branded_portal(): void
    {
        $this->post('/login', ['email' => $this->client->email, 'password' => 'portal-password-1'])->assertRedirect(route('portal.dashboard'));

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Bloom Bakery')->assertSee('--color-brand-600:#be185d', false);
    }

    public function test_clients_cannot_reach_any_team_page_or_other_clients(): void
    {
        $other = Workspace::factory()->create();
        $foreignCampaign = Campaign::factory()->for($other)->create(['status' => 'done']);
        $this->actingAs($this->client);

        foreach ([route('dashboard'), route('campaigns.index'), route('lists.index'), route('sending.servers.index'), route('api.settings'), route('workspaces.edit')] as $url) {
            $this->get($url)->assertRedirect(route('portal.dashboard'));
        }
        $this->post(route('workspaces.switch', $other->uid))->assertRedirect(route('portal.dashboard'));
        $this->post(route('campaigns.store'), ['type' => 'regular'])->assertRedirect(route('portal.dashboard'));
        $this->get(route('portal.campaign', $foreignCampaign))->assertNotFound();
        $this->assertSame(0, Campaign::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_portal_shows_sent_campaign_reports_but_not_drafts_or_contacts(): void
    {
        $list = MailList::factory()->for($this->workspace)->create(['name' => 'Bread lovers']);
        Subscriber::factory()->for($list, 'list')->create(['email' => 'private-person@example.com']);
        $sent = Campaign::factory()->for($this->workspace)->create(['name' => 'Autumn bake', 'status' => 'done', 'started_at' => now()]);
        $draft = Campaign::factory()->for($this->workspace)->create(['name' => 'Secret draft']);
        $this->actingAs($this->client);

        $this->get(route('portal.campaigns'))->assertOk()->assertSee('Autumn bake')->assertDontSee('Secret draft');
        $this->get(route('portal.campaign', $sent))->assertOk()->assertSee('Open rate');
        $this->get(route('portal.campaign', $draft))->assertNotFound();
        $this->get(route('portal.lists'))->assertOk()->assertSee('Bread lovers')->assertDontSee('private-person@example.com');
    }

    public function test_team_members_are_kept_out_of_the_portal(): void
    {
        $this->signInTo($this->workspace);

        $this->get(route('portal.dashboard'))->assertRedirect(route('dashboard'));
    }

    public function test_team_grants_and_revokes_portal_access_per_client(): void
    {
        $this->signInTo($this->workspace);

        $this->post(route('portal-users.store'), ['portal_name' => 'Ann', 'portal_email' => 'ann@bloom.test', 'portal_password' => 'short'])
            ->assertSessionHasErrors('portal_password');
        $this->post(route('portal-users.store'), ['portal_name' => 'Ann', 'portal_email' => 'ann@bloom.test', 'portal_password' => 'long-enough-pass'])->assertRedirect();

        $ann = User::where('email', 'ann@bloom.test')->first();
        $this->assertTrue($ann->isClient());
        $this->assertSame($this->workspace->id, $ann->workspace_id);
        $this->get(route('workspaces.edit'))->assertSee('ann@bloom.test');
        $this->get(route('team.index'))->assertDontSee('ann@bloom.test');

        $otherClientUser = User::factory()->create(['role' => 'client', 'workspace_id' => Workspace::factory()->create()->id]);
        $this->delete(route('portal-users.destroy', $otherClientUser))->assertNotFound();
        $this->delete(route('portal-users.destroy', $ann))->assertRedirect();
        $this->assertModelMissing($ann);
    }

    public function test_client_can_change_their_own_password(): void
    {
        $this->actingAs($this->client);

        $this->put(route('portal.account.update'), ['name' => 'New Name', 'email' => $this->client->email, 'current_password' => 'portal-password-1',
            'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'])->assertRedirect();

        $this->assertSame('New Name', $this->client->fresh()->name);
        $this->get(route('portal.account'))->assertOk();
    }
}

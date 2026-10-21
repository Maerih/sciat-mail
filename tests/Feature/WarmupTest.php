<?php

namespace Tests\Feature;

use App\Jobs\LaunchCampaign;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\WarmupStrategy;
use App\Models\Workspace;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarmupTest extends TestCase
{
    use RefreshDatabase;

    public function test_linear_and_exponential_quotas_ramp_from_initial_to_target(): void
    {
        $linear = new WarmupStrategy(['curve' => 'linear', 'initial_daily' => 100, 'target_daily' => 1000, 'days' => 10]);
        $this->assertSame(100, $linear->quotaForDay(1));
        $this->assertSame(500, $linear->quotaForDay(5));
        $this->assertSame(1000, $linear->quotaForDay(10));
        $this->assertSame(1000, $linear->quotaForDay(40), 'target holds after the warmup');

        $exponential = new WarmupStrategy(['curve' => 'exponential', 'initial_daily' => 100, 'target_daily' => 10000, 'days' => 3]);
        $this->assertSame([1 => 100, 2 => 1000, 3 => 10000], $exponential->schedule());
    }

    public function test_warmup_quota_caps_daily_sending_and_resumes_next_day(): void
    {
        $workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        $strategy = WarmupStrategy::create(['name' => 'Tiny', 'curve' => 'linear', 'initial_daily' => 3, 'target_daily' => 30, 'days' => 10]);
        $server = SendingServer::factory()->for($workspace)->create(['warmup_strategy_id' => $strategy->id, 'warmup_started_on' => now()->toDateString()]);
        $list = MailList::factory()->for($workspace)->create();
        Subscriber::factory()->for($list, 'list')->count(8)->create();
        $campaign = Campaign::factory()->for($workspace)->create();
        $campaign->audiences()->create(['mail_list_id' => $list->id]);

        $this->assertTrue($server->isWarmingUp());
        $this->assertSame(3, $server->effectiveDailyLimit());

        app(CampaignService::class)->launch($campaign);
        $this->assertCount(3, $this->transport->sent);

        $this->travel(1)->days();
        $this->assertSame(6, $server->fresh()->effectiveDailyLimit());
        LaunchCampaign::dispatchBatches($campaign->fresh());
        $this->assertCount(8, $this->transport->sent, 'day 2 allows 6 more, enough for the remaining 5');
    }

    public function test_daily_limit_wins_when_stricter_than_warmup(): void
    {
        $strategy = WarmupStrategy::create(['name' => 'Big', 'curve' => 'linear', 'initial_daily' => 500, 'target_daily' => 5000, 'days' => 10]);
        $server = SendingServer::factory()->create(['daily_limit' => 200, 'warmup_strategy_id' => $strategy->id, 'warmup_started_on' => now()->toDateString()]);

        $this->assertSame(200, $server->effectiveDailyLimit());
    }

    public function test_presets_are_read_only_and_custom_strategies_are_workspace_scoped(): void
    {
        $workspace = Workspace::factory()->create();
        $preset = WarmupStrategy::create(WarmupStrategy::PRESETS[0]);
        $foreign = WarmupStrategy::create(['workspace_id' => Workspace::factory()->create()->id, 'name' => 'Theirs', 'curve' => 'linear', 'initial_daily' => 1, 'target_daily' => 2, 'days' => 2]);
        $this->signInTo($workspace);

        $this->get(route('sending.warmup.index'))->assertOk()->assertSee($preset->name)->assertDontSee('Theirs');
        $this->get(route('sending.warmup.edit', $preset))->assertForbidden();
        $this->get(route('sending.warmup.edit', $foreign))->assertNotFound();

        $this->post(route('sending.warmup.store'), ['name' => 'Mine', 'curve' => 'exponential', 'initial_daily' => 50, 'target_daily' => 40, 'days' => 10])
            ->assertSessionHasErrors('target_daily');
        $this->post(route('sending.warmup.store'), ['name' => 'Mine', 'curve' => 'exponential', 'initial_daily' => 50, 'target_daily' => 4000, 'days' => 10])
            ->assertRedirect(route('sending.warmup.index'));
        $this->assertSame($workspace->id, WarmupStrategy::where('name', 'Mine')->value('workspace_id'));
    }

    public function test_attaching_a_strategy_to_a_server_defaults_the_start_to_today(): void
    {
        $workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        $server = SendingServer::factory()->for($workspace)->create();
        $strategy = WarmupStrategy::create(WarmupStrategy::PRESETS[1]);
        $this->signInTo($workspace);

        $this->put(route('sending.servers.update', $server), [
            'name' => $server->name, 'status' => 'active', 'warmup_strategy_id' => $strategy->id,
            'config' => ['host' => '127.0.0.1', 'port' => 25, 'encryption' => 'none'],
        ])->assertRedirect();

        $server->refresh();
        $this->assertSame($strategy->id, $server->warmup_strategy_id);
        $this->assertSame(now()->toDateString(), $server->warmup_started_on->toDateString());
        $this->assertSame(1, $server->warmupDay());
    }
}

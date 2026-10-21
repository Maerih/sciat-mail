<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AbTestingTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $this->list = MailList::factory()->for($this->workspace)->create();
        SendingServer::factory()->for($this->workspace)->create();
    }

    protected function abCampaign(int $recipients, array $config = ['test_percent' => 20, 'metric' => 'opens', 'wait_hours' => 4]): Campaign
    {
        Subscriber::factory()->for($this->list, 'list')->count($recipients)->create();
        $campaign = Campaign::factory()->for($this->workspace)->create(['type' => 'ab', 'subject' => 'Default subject', 'ab_config' => $config]);
        $campaign->audiences()->create(['mail_list_id' => $this->list->id]);
        $campaign->variants()->create(['label' => 'A', 'subject' => 'Subject A']);
        $campaign->variants()->create(['label' => 'B', 'subject' => 'Subject B']);

        return $campaign;
    }

    /** @return array<string, int> */
    protected function subjectCounts(): array
    {
        return collect($this->transport->sent)->countBy(fn ($m) => $m->getOriginalMessage()->getSubject())->sortKeys()->all();
    }

    public function test_only_the_test_group_is_sent_and_split_evenly_across_variants(): void
    {
        $campaign = $this->abCampaign(50);

        app(CampaignService::class)->launch($campaign);

        $campaign->refresh();
        $this->assertSame('testing', $campaign->status);
        $this->assertSame(['Subject A' => 5, 'Subject B' => 5], $this->subjectCounts());
        $this->assertSame(40, $campaign->deliveries()->where('status', 'pending')->whereNull('variant_id')->count());
        $this->assertTrue($campaign->test_ends_at->between(now()->addHours(4)->subMinute(), now()->addHours(4)->addMinute()));
    }

    public function test_variant_with_best_open_rate_wins_and_goes_to_everyone_else(): void
    {
        $campaign = $this->abCampaign(50);
        $service = app(CampaignService::class);
        $service->launch($campaign);
        $variantB = $campaign->variants()->where('label', 'B')->first();
        $campaign->deliveries()->where('variant_id', $variantB->id)->limit(3)->update(['opened_at' => now()]);

        $this->travel(5)->hours();
        $service->dispatchDue();

        $campaign->refresh();
        $this->assertSame($variantB->id, $campaign->winner_variant_id);
        $this->assertSame('done', $campaign->status);
        $this->assertSame(['Subject A' => 5, 'Subject B' => 45], $this->subjectCounts());
    }

    public function test_click_metric_decides_the_winner_when_configured(): void
    {
        $campaign = $this->abCampaign(20, ['test_percent' => 50, 'metric' => 'clicks', 'wait_hours' => 1]);
        $service = app(CampaignService::class);
        $service->launch($campaign);
        [$a, $b] = $campaign->variants()->orderBy('label')->get()->all();
        $campaign->deliveries()->where('variant_id', $a->id)->limit(4)->update(['opened_at' => now()]);
        $campaign->deliveries()->where('variant_id', $b->id)->limit(1)->update(['opened_at' => now(), 'clicked_at' => now()]);

        $this->assertSame($b->id, $service->pickWinner($campaign->fresh())->id);
    }

    public function test_manual_winner_ends_the_test_early(): void
    {
        $campaign = $this->abCampaign(30);
        app(CampaignService::class)->launch($campaign);
        $variantA = $campaign->variants()->where('label', 'A')->first();
        $this->signInTo($this->workspace);

        $this->post(route('campaigns.winner', $campaign), ['variant' => $variantA->id])->assertSessionHas('success');

        $this->assertSame($variantA->id, $campaign->fresh()->winner_variant_id);
        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertSame(30, count($this->transport->sent));
    }

    public function test_full_split_test_has_no_winner_phase(): void
    {
        $campaign = $this->abCampaign(10, ['test_percent' => 100, 'metric' => 'opens', 'wait_hours' => 4]);

        app(CampaignService::class)->launch($campaign);

        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertSame(['Subject A' => 5, 'Subject B' => 5], $this->subjectCounts());
    }

    public function test_variant_content_override_replaces_body_and_text(): void
    {
        $campaign = $this->abCampaign(4, ['test_percent' => 100, 'metric' => 'opens', 'wait_hours' => 1]);
        $campaign->variants()->where('label', 'B')->update(['html' => '<p>Completely different body</p>']);

        app(CampaignService::class)->launch($campaign);

        $bodies = collect($this->transport->sent)->map(fn ($m) => $m->getOriginalMessage());
        $this->assertSame(2, $bodies->filter(fn ($m) => str_contains($m->getHtmlBody(), 'Completely different body'))->count());
        $this->assertSame(2, $bodies->filter(fn ($m) => str_contains($m->getTextBody(), 'Completely different body'))->count());
    }

    public function test_paused_test_resumes_into_the_test_phase(): void
    {
        $campaign = $this->abCampaign(50);
        $service = app(CampaignService::class);
        SendingServer::query()->update(['hourly_limit' => 4]);
        $service->launch($campaign);
        $this->assertCount(4, $this->transport->sent);

        $service->pause($campaign->fresh());
        SendingServer::query()->update(['hourly_limit' => null]);
        Cache::flush();
        $service->resume($campaign->fresh());

        $this->assertSame('testing', $campaign->fresh()->status);
        $this->assertCount(10, $this->transport->sent, 'only the rest of the test group');
    }

    public function test_wizard_saves_variants_and_rejects_identical_ones(): void
    {
        $this->signInTo($this->workspace);
        $this->post(route('campaigns.store'), ['type' => 'ab'])->assertRedirect();
        $campaign = Campaign::first();
        $this->assertSame(2, $campaign->variants()->count());

        $this->post(route('campaigns.variants.save', $campaign), [
            'variants' => [['subject' => 'Same'], ['subject' => 'Same']],
            'test_percent' => 20, 'metric' => 'opens', 'wait_hours' => 4,
        ])->assertSessionHasErrors('variants');

        $this->post(route('campaigns.variants.save', $campaign), [
            'variants' => [['subject' => 'Short'], ['subject' => 'A much longer subject'], ['from_name' => 'The CEO']],
            'test_percent' => 30, 'metric' => 'clicks', 'wait_hours' => 6,
        ])->assertRedirect(route('campaigns.schedule', $campaign));

        $campaign->refresh();
        $this->assertSame(['A', 'B', 'C'], $campaign->variants->pluck('label')->all());
        $this->assertSame(['test_percent' => 30, 'metric' => 'clicks', 'wait_hours' => 6], $campaign->ab_config);
        $this->get(route('campaigns.variants', $campaign))->assertOk();
    }

    public function test_duplicate_copies_variants_without_the_result(): void
    {
        $campaign = $this->abCampaign(10);
        app(CampaignService::class)->launch($campaign);
        app(CampaignService::class)->pickWinner($campaign->fresh());

        $copy = $campaign->fresh()->duplicate();

        $this->assertSame(['A', 'B'], $copy->variants->pluck('label')->all());
        $this->assertNull($copy->winner_variant_id);
        $this->assertSame('draft', $copy->status);
    }
}

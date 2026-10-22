<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\ContentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $list = MailList::factory()->for($this->workspace)->create();
        $this->campaign = Campaign::factory()->for($this->workspace)->create(['status' => 'done', 'started_at' => now()->subDay()]);
        foreach (['US', 'US', 'KE', null] as $i => $country) {
            $subscriber = Subscriber::factory()->for($list, 'list')->create();
            $delivery = Delivery::create(['campaign_id' => $this->campaign->id, 'subscriber_id' => $subscriber->id, 'workspace_id' => $this->workspace->id, 'email' => $subscriber->email, 'status' => 'sent']);
            $delivery->events()->create(['type' => 'open', 'country' => $country, 'campaign_id' => $this->campaign->id]);
            if ($i < 2) {
                $delivery->events()->create(['type' => 'click', 'url' => 'https://shop.test/sale', 'country' => $country, 'campaign_id' => $this->campaign->id]);
            }
        }
        $this->signInTo($this->workspace);
    }

    public function test_links_tab_ranks_clicked_links(): void
    {
        $this->get(route('campaigns.links', $this->campaign))->assertOk()->assertSee('https://shop.test/sale');
    }

    public function test_map_tab_groups_unique_readers_by_country(): void
    {
        $response = $this->get(route('campaigns.map', $this->campaign))->assertOk()->assertSee('United States')->assertSee('Kenya');

        $countries = $response->viewData('countries');
        $this->assertSame(['US', 'KE'], $countries->pluck('code')->all());
        $this->assertSame(2, $countries->first()->opens);
        $this->assertSame(1, $response->viewData('unknown'));
    }

    public function test_review_flags_common_content_problems(): void
    {
        $campaign = Campaign::factory()->for($this->workspace)->create([
            'subject' => 'ACT NOW!!! FREE MONEY', 'preheader' => null,
            'html' => '<p>Click here to buy now, guaranteed winner</p><img src="https://cdn.test/a.png"><a href="#">x</a><a href="https://broken.test">y</a>',
        ]);
        $checks = collect(app(ContentReview::class)->review($campaign))->keyBy('label');

        $this->assertSame('warning', $checks['Capital letters']['level']);
        $this->assertSame('warning', $checks['Punctuation']['level']);
        $this->assertSame('warning', $checks['Preheader']['level']);
        $this->assertSame('error', $checks['Spam-trigger words']['level']);
        $this->assertSame('warning', $checks['Unsubscribe link']['level']);
        $this->assertSame('warning', $checks['Image alt text']['level']);
        $this->assertSame('warning', $checks['Links']['level']);
        $this->assertLessThan(60, ContentReview::score($checks->values()->all()));

        Http::fake(['broken.test*' => Http::response('', 404)]);
        $checks = collect(app(ContentReview::class)->review($campaign, checkLinks: true))->keyBy('label');
        $this->assertSame('error', $checks['Broken links']['level']);

        $this->get(route('campaigns.review', $campaign))->assertOk()->assertSee('Needs work');
    }

    public function test_clean_email_scores_well(): void
    {
        $campaign = Campaign::factory()->for($this->workspace)->create([
            'subject' => 'Your October product update', 'preheader' => 'Three new features inside',
            'html' => '<p>'.str_repeat('Helpful words about our product. ', 20).'</p><img src="https://cdn.test/a.png" alt="Screenshot"><a href="{{ UNSUBSCRIBE_URL }}">Unsubscribe</a>',
        ]);

        $this->assertGreaterThanOrEqual(85, ContentReview::score(app(ContentReview::class)->review($campaign)));
    }
}

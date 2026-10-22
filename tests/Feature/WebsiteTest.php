<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Website;
use App\Models\Workspace;
use App\Sending\TrackingUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $this->list = MailList::factory()->for($this->workspace)->create();
    }

    protected function website(array $attributes = []): Website
    {
        $website = new Website(['name' => 'Shop', 'url' => 'https://www.shop.test'] + $attributes);
        $website->workspace_id = $this->workspace->id;
        $website->save();

        return $website->fresh();
    }

    public function test_team_can_add_a_website_and_see_its_tracking_code(): void
    {
        $this->signInTo($this->workspace);

        $this->get(route('websites.index'))->assertOk()->assertSee('Connect your websites');
        $this->post(route('websites.store'), ['url' => 'https://www.acme.test/'])->assertRedirect();

        $website = Website::firstOrFail();
        $this->assertSame('acme.test', $website->host);
        $this->assertSame('www.acme.test', parse_url($website->url, PHP_URL_HOST));
        $this->assertSame('acme.test', $website->name);
        $this->get(route('websites.show', $website))->assertOk()->assertSee('/site/'.$website->uid.'/connect.js', false);
    }

    public function test_websites_of_other_clients_are_not_reachable(): void
    {
        $website = $this->website();
        $this->signInTo(Workspace::factory()->create());

        $this->get(route('websites.show', $website))->assertNotFound();
    }

    public function test_connect_checks_the_site_for_the_tracking_code(): void
    {
        $website = $this->website();
        $this->signInTo($this->workspace);

        Http::fake(['*' => Http::sequence()
            ->push('<html><body>Hello</body></html>')
            ->push('<html><body>'.$website->snippet().'</body></html>')]);
        $this->post(route('websites.verify', $website))->assertSessionHas('error');
        $this->assertFalse($website->fresh()->isConnected());

        $this->post(route('websites.verify', $website))->assertSessionHas('success');
        $this->assertTrue($website->fresh()->isConnected());
    }

    public function test_script_loads_connected_published_forms(): void
    {
        $website = $this->website();
        $popup = $this->list->forms()->create(['name' => 'Popup', 'title' => 'Get 10% off', 'display' => 'popup']);
        $inline = $this->list->forms()->create(['name' => 'Footer', 'display' => 'embedded']);
        $draft = $this->list->forms()->create(['name' => 'Draft', 'status' => 'draft']);
        foreach ([$popup, $inline, $draft] as $form) {
            $form->website()->associate($website)->save();
        }

        $js = $this->get(route('site.script', $website->uid))->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->getContent();

        $this->assertStringContainsString($popup->uid, $js);
        $this->assertStringContainsString('Get 10% off', $js);
        $this->assertStringContainsString('"'.$inline->uid.'"', $js);
        $this->assertStringNotContainsString($draft->uid, $js);
        $this->assertStringContainsString(route('site.collect', $website->uid), $js);
    }

    public function test_collector_records_visits_only_from_the_site_and_marks_it_connected(): void
    {
        $website = $this->website();
        $url = route('site.collect', $website->uid);
        $body = json_encode(['v' => 'abc123', 'p' => '/pricing', 'r' => 'https://google.com']);

        $this->call('POST', $url, [], [], [], ['HTTP_ORIGIN' => 'https://evil.test', 'CONTENT_TYPE' => 'text/plain'], $body)->assertNoContent();
        $this->assertSame(0, $website->visits()->count());

        $this->call('POST', $url, [], [], [], ['HTTP_ORIGIN' => 'https://blog.shop.test', 'CONTENT_TYPE' => 'text/plain'], $body)->assertNoContent();

        $visit = $website->visits()->sole();
        $this->assertSame('/pricing', $visit->path);
        $this->assertSame('abc123', $visit->visitor);
        $this->assertNull($visit->subscriber_id);
        $this->assertTrue($website->fresh()->isConnected());
    }

    public function test_email_clicks_to_the_site_identify_the_contact(): void
    {
        $website = $this->website();
        $subscriber = Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = Campaign::factory()->for($this->workspace)->create(['status' => 'done']);
        $delivery = Delivery::create(['campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id, 'workspace_id' => $this->workspace->id, 'email' => $subscriber->email, 'status' => 'sent']);
        $urls = app(TrackingUrls::class);

        // Links elsewhere are left untouched
        $this->get($urls->click($delivery, 'https://other.test/page'))->assertRedirect('https://other.test/page');

        $location = $this->get($urls->click($delivery, 'https://www.shop.test/sale?x=1#top'))->headers->get('Location');
        $this->assertMatchesRegularExpression('~^https://www\.shop\.test/sale\?x=1&_sm=[A-Za-z0-9]+\.[a-f0-9]{20}#top$~', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->call('POST', route('site.collect', $website->uid), [], [], [], ['HTTP_ORIGIN' => 'https://www.shop.test', 'CONTENT_TYPE' => 'text/plain'],
            json_encode(['v' => 'v1', 'id' => $query['_sm'], 'p' => '/sale']));
        $this->assertSame($subscriber->id, $website->visits()->sole()->subscriber_id);

        // A tampered id is ignored
        $this->call('POST', route('site.collect', $website->uid), [], [], [], ['HTTP_ORIGIN' => 'https://www.shop.test', 'CONTENT_TYPE' => 'text/plain'],
            json_encode(['v' => 'v1', 'id' => $delivery->uid.'.0000000000aaaaaaaaaa', 'p' => '/']));
        $this->assertNull($website->visits()->latest('id')->first()->subscriber_id);

        $this->signInTo($this->workspace);
        $this->get(route('websites.show', $website))->assertOk()->assertSee($subscriber->email);
    }

    public function test_forms_can_be_connected_and_disconnected(): void
    {
        $website = $this->website();
        $form = $this->list->forms()->create(['name' => 'Footer']);
        $this->signInTo($this->workspace);

        $this->post(route('websites.forms.connect', $website), ['form' => $form->uid])->assertRedirect();
        $this->assertSame($website->id, $form->fresh()->website_id);
        $this->get(route('forms.index'))->assertSee('Shop');

        $this->delete(route('websites.forms.disconnect', [$website, $form->uid]))->assertRedirect();
        $this->assertNull($form->fresh()->website_id);
    }

    public function test_changing_the_domain_disconnects_the_site(): void
    {
        $website = $this->website();
        $website->forceFill(['status' => 'connected', 'connected_at' => now()])->save();
        $this->signInTo($this->workspace);

        $this->put(route('websites.update', $website), ['url' => 'https://www.shop.test', 'name' => 'Shop', 'track_visits' => '1']);
        $this->assertTrue($website->fresh()->isConnected());

        $this->put(route('websites.update', $website), ['url' => 'https://new-shop.test', 'name' => 'Shop']);
        $this->assertFalse($website->fresh()->isConnected());
        $this->assertFalse($website->fresh()->track_visits);
    }
}

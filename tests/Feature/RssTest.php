<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Automation\Engine;
use App\Services\CampaignService;
use App\Services\RssFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssTest extends TestCase
{
    use RefreshDatabase;

    protected function rss(array $posts): string
    {
        $items = collect($posts)->map(fn ($p) => '<item><title>'.htmlspecialchars($p[0], ENT_XML1)."</title><link>https://blog.test/{$p[1]}</link><guid>https://blog.test/{$p[1]}</guid>"
            .'<description><![CDATA[<p>Summary of '.htmlspecialchars($p[0]).' &amp; more</p>]]></description>'.'<pubDate>Mon, 19 Oct 2026 10:00:00 +0000</pubDate></item>')->implode('');

        return '<?xml version="1.0"?><rss version="2.0"><channel><title>Blog</title>'.$items.'</channel></rss>';
    }

    public function test_rss_and_atom_feeds_are_parsed(): void
    {
        Http::fake([
            'blog.test/rss' => Http::response($this->rss([['First <post>', 'first'], ['Second', 'second']])),
            'blog.test/atom' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><id>tag:1</id><title>Atom post</title>'
                .'<link rel="alternate" href="https://blog.test/atom-post"/><summary>Hello atom</summary><updated>2026-10-20T08:00:00Z</updated></entry></feed>'),
            'blog.test/broken' => Http::response('not xml', 200),
        ]);
        $feed = app(RssFeed::class);

        $rss = $feed->items('https://blog.test/rss', 5);
        $this->assertSame('First <post>', $rss[0]['title']);
        $this->assertSame('https://blog.test/first', $rss[0]['link']);
        $this->assertSame('Summary of First <post> & more', $rss[0]['summary']);
        $this->assertSame('Oct 19, 2026', $rss[0]['date']);

        $this->assertSame([['id' => 'tag:1', 'title' => 'Atom post', 'link' => 'https://blog.test/atom-post', 'summary' => 'Hello atom', 'date' => 'Oct 20, 2026', 'image' => null]],
            $feed->items('https://blog.test/atom'));
        $this->assertSame([], $feed->items('https://blog.test/broken'));
    }

    public function test_campaign_renders_rss_tags_with_safe_html(): void
    {
        Http::fake(['blog.test/*' => Http::response($this->rss([['Big <b>news</b>', 'news'], ['Older', 'older']]))]);
        $workspace = Workspace::factory()->create();
        SendingServer::factory()->for($workspace)->create();
        $list = MailList::factory()->for($workspace)->create();
        Subscriber::factory()->for($list, 'list')->count(2)->create();
        $campaign = Campaign::factory()->for($workspace)->create([
            'subject' => 'New: {{ RSS_LATEST_TITLE }}',
            'html' => '<p>{{ RSS_ITEMS }}</p><a href="{{ RSS_LATEST_URL }}">Read</a>',
            'rss' => ['url' => 'https://blog.test/feed', 'count' => 1],
            'track_click' => false,
        ]);
        $campaign->audiences()->create(['mail_list_id' => $list->id]);

        app(CampaignService::class)->launch($campaign);

        $message = $this->transport->sent[0]->getOriginalMessage();
        $this->assertSame('New: Big <b>news</b>', $message->getSubject());
        $this->assertStringContainsString('Big &lt;b&gt;news&lt;/b&gt;', $message->getHtmlBody(), 'feed text is escaped inside the rendered block');
        $this->assertStringContainsString('<table role="presentation"', $message->getHtmlBody(), 'the block itself is inserted as HTML');
        $this->assertStringNotContainsString('Older', $message->getHtmlBody());
        $this->assertStringContainsString('href="https://blog.test/news"', $message->getHtmlBody());
        Http::assertSentCount(1);
    }

    public function test_rss_automation_baselines_then_sends_only_for_new_posts(): void
    {
        $posts = [['Launch', 'launch']];
        Http::fake(['blog.test/*' => function () use (&$posts) {
            return Http::response($this->rss($posts));
        }]);
        $workspace = Workspace::factory()->create();
        SendingServer::factory()->for($workspace)->create();
        $list = MailList::factory()->for($workspace)->create();
        Subscriber::factory()->for($list, 'list')->count(2)->create();
        $automation = Automation::create([
            'workspace_id' => $workspace->id, 'mail_list_id' => $list->id, 'name' => 'Blog digest', 'status' => 'active',
            'trigger_type' => 'rss', 'trigger_config' => ['url' => 'https://blog.test/feed', 'count' => 3, 'last_item' => null],
        ]);
        $email = Campaign::factory()->for($workspace)->create(['automation_id' => $automation->id, 'status' => 'automation', 'subject' => 'New post: {{ RSS_LATEST_TITLE }}']);
        $flow = $automation->flow();
        $flow->insert(null, 'next', ['type' => 'send', 'campaign_id' => $email->id]);
        $automation->setFlow($flow);
        $automation->save();
        $engine = app(Engine::class);

        $engine->tick();
        $this->assertSame([], $this->transport->sent, 'first check only remembers the newest post');
        $this->assertSame('https://blog.test/launch', $automation->fresh()->config('last_item'));

        Cache::flush();
        $engine->tick();
        $this->assertSame([], $this->transport->sent, 'no new post, nothing sent');

        $posts = [['Second post', 'second'], ['Launch', 'launch']];
        Cache::flush();
        $engine->tick();
        $this->assertCount(2, $this->transport->sent);
        $this->assertSame('New post: Second post', $this->transport->sent[0]->getOriginalMessage()->getSubject());

        Cache::flush();
        $engine->tick();
        $this->assertCount(2, $this->transport->sent, 'the same post is never sent twice');
    }

    public function test_rss_trigger_requires_a_feed_url(): void
    {
        $workspace = Workspace::factory()->create();
        $list = MailList::factory()->for($workspace)->create();
        $this->signInTo($workspace);

        $this->post(route('automations.store'), ['name' => 'Digest', 'list' => $list->uid, 'trigger_type' => 'rss'])
            ->assertSessionHasErrors('config.url');
        $this->post(route('automations.store'), ['name' => 'Digest', 'list' => $list->uid, 'trigger_type' => 'rss', 'config' => ['url' => 'https://blog.test/feed', 'count' => 4]])
            ->assertRedirect();
        $this->assertSame(['url' => 'https://blog.test/feed', 'count' => 4, 'last_item' => null], Automation::first()->trigger_config);
    }
}

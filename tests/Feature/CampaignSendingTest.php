<?php

namespace Tests\Feature;

use App\Jobs\LaunchCampaign;
use App\Jobs\SendCampaignBatch;
use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingDomain;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CampaignSendingTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['address' => '1 Main St, Nairobi']);
        $this->list = MailList::factory()->for($this->workspace)->create();
        SendingServer::factory()->for($this->workspace)->create();
    }

    protected function campaignFor(array $attributes = [], ?MailList $list = null): Campaign
    {
        $campaign = Campaign::factory()->for($this->workspace)->create($attributes);
        $campaign->audiences()->create(['mail_list_id' => ($list ?? $this->list)->id]);

        return $campaign;
    }

    public function test_launch_sends_personalised_email_to_every_subscribed_recipient(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'ann@example.com', 'data' => ['FIRST_NAME' => 'Ann']]);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'bob@example.com', 'data' => []]);
        Subscriber::factory()->for($this->list, 'list')->status('unsubscribed')->create();
        Subscriber::factory()->for($this->list, 'list')->status('unconfirmed')->create();

        $campaign = $this->campaignFor();
        app(CampaignService::class)->launch($campaign);

        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertEqualsCanonicalizing(['ann@example.com', 'bob@example.com'], $this->transport->recipients());

        $message = collect($this->transport->sent)->first(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress() === 'ann@example.com')->getOriginalMessage();
        $this->assertSame('Hello Ann', $message->getSubject());
        $this->assertStringContainsString('Hi Ann', $message->getHtmlBody());
        $this->assertSame(2, $campaign->fresh()->sent_count);
        $bob = collect($this->transport->sent)->first(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress() === 'bob@example.com')->getOriginalMessage();
        $this->assertSame('Hello friend', $bob->getSubject(), 'merge tag fallback is used for empty fields');
    }

    public function test_messages_carry_tracking_compliance_footer_and_one_click_unsubscribe_headers(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'ann@example.com']);
        $campaign = $this->campaignFor(['utm' => ['enabled' => true, 'source' => 'news', 'medium' => 'email']]);

        app(CampaignService::class)->launch($campaign);

        $message = $this->transport->sent[0]->getOriginalMessage();
        $delivery = $campaign->deliveries()->first();
        $html = $message->getHtmlBody();

        $this->assertStringContainsString('/t/o/'.$delivery->uid.'.gif', $html);
        $this->assertStringContainsString('/t/c/'.$delivery->uid.'/', $html);
        $this->assertStringNotContainsString('href="https://example.com/offer"', $html, 'links are wrapped for click tracking');
        $this->assertStringContainsString('1 Main St, Nairobi', $html, 'postal address footer is appended');
        $this->assertStringContainsString('/u/'.$delivery->uid.'/', $html, 'unsubscribe link is appended');
        $this->assertSame('List-Unsubscribe=One-Click', $message->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertStringContainsString('/u/'.$delivery->uid.'/', $message->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringNotContainsString('/t/o/', $message->getTextBody(), 'plain text part has no tracking pixel');
    }

    public function test_utm_parameters_are_added_to_links_but_not_to_own_links(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = $this->campaignFor([
            'track_click' => false,
            'html' => '<p><a href="https://example.com/a?x=1">A</a> <a href="{{ WEB_VIEW_URL }}">View</a></p>',
            'utm' => ['enabled' => true, 'source' => 'news', 'medium' => 'email'],
        ]);

        app(CampaignService::class)->launch($campaign);

        $html = $this->transport->sent[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('https://example.com/a?x=1&amp;utm_source=news&amp;utm_medium=email', $html);
        $this->assertMatchesRegularExpression('#href="http://[^"]+/w/[a-z0-9]+/[a-f0-9]+"#', $html, 'web view link is untouched');
    }

    public function test_duplicate_emails_across_lists_receive_one_copy_and_suppressed_addresses_none(): void
    {
        $second = MailList::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'dup@example.com']);
        Subscriber::factory()->for($second, 'list')->create(['email' => 'dup@example.com']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'blocked@example.com']);
        Blacklist::create(['workspace_id' => null, 'email' => 'blocked@example.com', 'reason' => 'manual']);

        $campaign = $this->campaignFor();
        $campaign->audiences()->create(['mail_list_id' => $second->id]);
        app(CampaignService::class)->launch($campaign);

        $this->assertSame(['dup@example.com'], $this->transport->recipients());
    }

    public function test_excluded_audience_is_removed_from_recipients(): void
    {
        $customers = MailList::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'lead@example.com']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'customer@example.com']);
        Subscriber::factory()->for($customers, 'list')->create(['email' => 'customer@example.com']);

        $campaign = $this->campaignFor();
        $campaign->audiences()->create(['mail_list_id' => $customers->id, 'exclude' => true]);

        $this->assertSame(1, $campaign->load('audiences.segment')->estimateRecipients());
        app(CampaignService::class)->launch($campaign);
        $this->assertSame(['lead@example.com'], $this->transport->recipients());
    }

    public function test_subscribers_of_another_workspace_are_never_targeted(): void
    {
        $foreignList = MailList::factory()->create();
        Subscriber::factory()->for($foreignList, 'list')->create();

        $campaign = $this->campaignFor([], $foreignList);
        $this->assertSame(0, $campaign->load('audiences.segment')->estimateRecipients());
    }

    public function test_scheduled_campaign_waits_until_due(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = $this->campaignFor(['scheduled_at' => now()->addHour()]);
        $service = app(CampaignService::class);

        $service->launch($campaign);
        $this->assertSame('scheduled', $campaign->fresh()->status);
        $this->assertSame(0, $service->dispatchDue());
        $this->assertSame([], $this->transport->sent);

        $this->travel(61)->minutes();
        $this->assertSame(1, $service->dispatchDue());
        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertCount(1, $this->transport->sent);
    }

    public function test_sending_window_defers_sending_outside_allowed_hours(): void
    {
        $this->travelTo(now()->startOfWeek()->setTime(3, 0)); // Monday 03:00 UTC
        Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = $this->campaignFor(['sending_window' => [['weekday' => 1, 'start' => '09:00', 'end' => '17:00']]]);

        $this->assertFalse($campaign->inSendingWindow());
        $this->assertSame(6 * 3600, $campaign->secondsUntilWindowOpens());

        $this->travelTo(now()->setTime(10, 0));
        $this->assertTrue($campaign->fresh()->inSendingWindow());
    }

    public function test_server_hourly_limit_stops_sending_once_reached(): void
    {
        SendingServer::query()->update(['hourly_limit' => 2]);
        Subscriber::factory()->for($this->list, 'list')->count(5)->create();
        $campaign = $this->campaignFor();

        app(CampaignService::class)->launch($campaign);

        $this->assertCount(2, $this->transport->sent);
        $this->assertSame(3, $campaign->deliveries()->where('status', 'pending')->count());
        $this->assertSame('sending', $campaign->fresh()->status);
    }

    public function test_permanent_failure_marks_delivery_failed_and_skip_failed_keeps_going(): void
    {
        Subscriber::factory()->for($this->list, 'list')->count(2)->create();
        $this->transport->failWith = '550 5.1.1 Mailbox does not exist';
        $campaign = $this->campaignFor(['skip_failed' => true]);

        app(CampaignService::class)->launch($campaign);

        $this->assertSame(2, $campaign->deliveries()->where('status', 'failed')->count());
        $this->assertSame('done', $campaign->fresh()->status);
    }

    public function test_permanent_failure_halts_campaign_when_skip_failed_is_off(): void
    {
        Subscriber::factory()->for($this->list, 'list')->count(3)->create();
        $this->transport->failWith = '550 Relaying denied';
        $campaign = $this->campaignFor(['skip_failed' => false]);

        app(CampaignService::class)->launch($campaign);

        $campaign->refresh();
        $this->assertSame('error', $campaign->status);
        $this->assertStringContainsString('Relaying denied', $campaign->last_error);
        $this->assertSame(2, $campaign->deliveries()->where('status', 'pending')->count());
    }

    public function test_transient_failure_is_retried_up_to_the_attempt_limit(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create();
        $this->transport->failWith = '421 4.7.0 Try again later';
        $campaign = $this->campaignFor();

        // The sync queue runs the delayed retry jobs immediately
        app(CampaignService::class)->launch($campaign);

        $delivery = $campaign->deliveries()->first();
        $this->assertSame(SendCampaignBatch::MAX_ATTEMPTS, $delivery->attempts);
        $this->assertSame('failed', $delivery->status);
    }

    public function test_permanent_failure_is_not_retried(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create();
        $this->transport->failWith = '550 5.1.1 User unknown';
        $campaign = $this->campaignFor();

        app(CampaignService::class)->launch($campaign);

        $this->assertSame(1, $campaign->deliveries()->first()->attempts);
    }

    public function test_paused_campaign_resumes_without_resending(): void
    {
        Subscriber::factory()->for($this->list, 'list')->count(3)->create();
        $campaign = $this->campaignFor();
        $service = app(CampaignService::class);
        SendingServer::query()->update(['hourly_limit' => 1]);

        $service->launch($campaign);
        $this->assertCount(1, $this->transport->sent);
        $service->pause($campaign->fresh());

        SendingServer::query()->update(['hourly_limit' => null]);
        Cache::flush();
        $service->resume($campaign->fresh());

        $this->assertCount(3, $this->transport->sent);
        $this->assertCount(3, array_unique($this->transport->recipients()));
        $this->assertSame('done', $campaign->fresh()->status);
    }

    public function test_no_active_server_puts_campaign_in_error(): void
    {
        SendingServer::query()->update(['status' => 'inactive']);
        Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = $this->campaignFor();
        $campaign->forceFill(['status' => 'queued'])->save();

        LaunchCampaign::dispatchSync($campaign->id);

        $this->assertSame('error', $campaign->fresh()->status);
    }

    public function test_smtp_mail_from_a_verified_domain_is_dkim_signed(): void
    {
        $domain = SendingDomain::create(['workspace_id' => $this->workspace->id, 'name' => 'example.com']);
        $domain->forceFill(['ownership_verified' => true, 'dkim_verified' => true])->save();
        Subscriber::factory()->for($this->list, 'list')->create();
        $campaign = $this->campaignFor(['from_email' => 'news@example.com']);

        app(CampaignService::class)->launch($campaign);

        $signature = $this->transport->sent[0]->getOriginalMessage()->getHeaders()->get('DKIM-Signature')?->getBodyAsString();
        $this->assertNotNull($signature);
        $this->assertStringContainsString('d=example.com', $signature);
        $this->assertStringContainsString('s='.$domain->dkim_selector, $signature);
    }

    public function test_api_drivers_are_not_signed_with_our_key(): void
    {
        SendingServer::query()->update(['type' => 'sendgrid-api']);
        $domain = SendingDomain::create(['workspace_id' => $this->workspace->id, 'name' => 'example.com']);
        $domain->forceFill(['ownership_verified' => true, 'dkim_verified' => true])->save();
        Subscriber::factory()->for($this->list, 'list')->create();

        app(CampaignService::class)->launch($this->campaignFor(['from_email' => 'news@example.com']));

        $this->assertNull($this->transport->sent[0]->getOriginalMessage()->getHeaders()->get('DKIM-Signature'));
    }

    public function test_plain_text_campaign_gets_text_footer(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['data' => ['FIRST_NAME' => 'Ann']]);
        $campaign = $this->campaignFor(['type' => 'plain', 'html' => null, 'plain' => 'Hi {{ SUBSCRIBER_FIRST_NAME }}, quick note.']);

        app(CampaignService::class)->launch($campaign);

        $message = $this->transport->sent[0]->getOriginalMessage();
        $this->assertNull($message->getHtmlBody());
        $this->assertStringStartsWith('Hi Ann, quick note.', $message->getTextBody());
        $this->assertStringContainsString('Unsubscribe: http', $message->getTextBody());
    }
}

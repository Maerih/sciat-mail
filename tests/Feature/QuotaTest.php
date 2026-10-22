<?php

namespace Tests\Feature;

use App\Jobs\LaunchCampaign;
use App\Models\ApiToken;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\CampaignService;
use App\Services\Quota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        $this->list = MailList::factory()->for($this->workspace)->create();
        SendingServer::factory()->for($this->workspace)->create();
    }

    protected function campaign(): Campaign
    {
        $campaign = Campaign::factory()->for($this->workspace)->create();
        $campaign->audiences()->create(['mail_list_id' => $this->list->id]);

        return $campaign->load('audiences.segment');
    }

    public function test_launch_is_blocked_when_recipients_exceed_the_monthly_email_limit(): void
    {
        $this->workspace->forceFill(['monthly_email_limit' => 2])->save();
        Subscriber::factory()->for($this->list, 'list')->count(3)->create();
        $campaign = $this->campaign();

        $check = collect(app(CampaignService::class)->preflight($campaign))->firstWhere('key', 'quota');
        $this->assertSame('error', $check['level']);
        $this->assertFalse(app(CampaignService::class)->canLaunch($campaign));
    }

    public function test_sending_pauses_when_the_limit_is_reached_mid_campaign(): void
    {
        Subscriber::factory()->for($this->list, 'list')->count(4)->create();
        $campaign = $this->campaign();
        app(CampaignService::class)->launch($campaign);
        $this->assertCount(4, $this->transport->sent);

        // A second campaign with only 1 email left this month
        $this->workspace->forceFill(['monthly_email_limit' => 5])->save();
        $second = $this->campaign()->fresh();
        $second->forceFill(['status' => 'queued'])->save();
        LaunchCampaign::dispatchSync($second->id);

        $this->assertCount(5, $this->transport->sent);
        $this->assertSame('paused', $second->fresh()->status);
        $this->assertStringContainsString('monthly email limit', $second->fresh()->last_error);
        $this->assertSame(0, Quota::emailsRemaining($this->workspace));
    }

    public function test_usage_resets_with_the_new_month(): void
    {
        $this->workspace->forceFill(['monthly_email_limit' => 10])->save();
        Subscriber::factory()->for($this->list, 'list')->count(3)->create();
        app(CampaignService::class)->launch($this->campaign());
        $this->assertSame(7, Quota::emailsRemaining($this->workspace));

        $this->travelTo(now()->addMonth()->startOfMonth()->addHour());
        $this->assertSame(10, Quota::emailsRemaining($this->workspace));
    }

    public function test_contact_limit_blocks_new_contacts_everywhere_but_allows_updates(): void
    {
        $this->workspace->forceFill(['contact_limit' => 1])->save();
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'existing@example.com']);
        $form = $this->list->forms()->create(['name' => 'Footer']);

        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'new@example.com'])->assertStatus(422);
        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'existing@example.com'])->assertOk();

        [, $token] = ApiToken::issue($this->workspace, 't');
        $this->withToken($token)->postJson("/api/v1/lists/{$this->list->uid}/subscribers", ['email' => 'api@example.com'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'contact limit'));

        $this->signInTo($this->workspace);
        $this->post(route('lists.subscribers.store', $this->list), ['EMAIL' => 'manual@example.com'])->assertSessionHasErrors('EMAIL');
        $this->assertSame(1, $this->list->subscribers()->count());
    }

    public function test_import_stops_adding_at_the_contact_limit_and_says_so(): void
    {
        $this->workspace->forceFill(['contact_limit' => 2])->save();
        $this->signInTo($this->workspace);
        $csv = "email\na@example.com\nb@example.com\nc@example.com\n";

        $this->post(route('lists.import.upload', $this->list), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)]);
        $import = $this->list->imports()->first();
        $this->post(route('lists.import.start', [$this->list, $import]), ['mapping' => ['EMAIL']]);

        $import->refresh();
        $this->assertSame(2, $import->created_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertStringContainsString('contact limit', $import->errors[0]);
    }

    public function test_only_admins_set_limits_and_dashboard_warns_near_them(): void
    {
        $this->signInTo($this->workspace);
        $this->put(route('workspaces.update'), ['name' => 'X', 'timezone' => 'UTC', 'monthly_email_limit' => 5])->assertRedirect();
        $this->assertNull($this->workspace->fresh()->monthly_email_limit, 'members cannot change limits');

        $this->signInTo($this->workspace, 'admin');
        $this->put(route('workspaces.update'), ['name' => 'X', 'timezone' => 'UTC', 'contact_limit' => 10])->assertRedirect();
        $this->assertSame(10, $this->workspace->fresh()->contact_limit);

        Subscriber::factory()->for($this->list, 'list')->count(9)->create();
        $this->get(route('dashboard'))->assertOk()->assertSee('close to or over its contact limit')->assertSee('10 max');
        $this->get(route('workspaces.index'))->assertOk()->assertSee('9 / 10');
    }
}

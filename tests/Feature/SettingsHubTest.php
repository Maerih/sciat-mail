<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CampaignService;
use App\Services\SendingHealth;
use App\Services\SystemMailConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettingsHubTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC']);
    }

    public function test_every_settings_tab_renders_for_admins(): void
    {
        $this->signInTo($this->workspace, 'admin');

        foreach (['general', 'mail', 'sending', 'health', 'cron', 'system'] as $tab) {
            $this->get(route('settings.show', $tab))->assertOk()->assertSee('Settings');
        }
        $this->get(route('settings.show', 'ai'))->assertRedirect(route('platform.ai'));
        $this->get(route('settings.index'))->assertRedirect(route('settings.show', 'general'));
    }

    public function test_members_cannot_open_settings(): void
    {
        $this->signInTo($this->workspace);

        $this->get(route('settings.show', 'general'))->assertForbidden();
        $this->put(route('settings.sending'), [])->assertForbidden();
    }

    public function test_sending_defaults_are_saved_and_applied_to_new_campaigns(): void
    {
        $this->signInTo($this->workspace, 'admin');

        $this->put(route('settings.sending'), ['one_click_unsubscribe' => '1', 'default_track_open' => '1'])->assertRedirect();

        $this->assertFalse(Setting::bool('default_track_click', true));
        $this->assertSame(['track_open' => true, 'track_click' => false, 'skip_failed' => false], Campaign::defaultOptions());
    }

    public function test_offline_mode_blocks_members_but_not_admins(): void
    {
        Setting::put(['site_online' => '0', 'offline_message' => 'Back at noon']);

        $this->signInTo($this->workspace);
        $this->get(route('dashboard'))->assertStatus(503)->assertSee('Back at noon');

        $this->signInTo($this->workspace, 'admin');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_system_mail_settings_encrypt_the_secret_and_configure_the_mailer(): void
    {
        $this->signInTo($this->workspace, 'admin');

        $this->put(route('settings.mail'), [
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.com', 'mail_port' => 2525, 'mail_encryption' => 'tls',
            'mail_username' => 'robot', 'mail_password' => 's3cret', 'mail_from_address' => 'noreply@example.com', 'mail_from_name' => 'Team',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('s3cret', Setting::get('mail_password'));
        $this->assertSame('s3cret', Crypt::decryptString(Setting::get('mail_password')));

        // Saving again with a blank password keeps the stored secret
        $this->put(route('settings.mail'), ['mail_driver' => 'smtp', 'mail_host' => 'smtp.example.com', 'mail_port' => 2525, 'mail_from_address' => 'noreply@example.com']);
        $this->assertSame('s3cret', Crypt::decryptString(Setting::get('mail_password')));

        SystemMailConfig::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('s3cret', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pat@example.com']);

        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => 'pat@example.com'])->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get(route('password.reset', $token))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'pat@example.com', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }

    protected function seedTraffic(int $sent, int $hardBounces, int $softBounces = 0, int $complaints = 0): Campaign
    {
        $list = MailList::factory()->for($this->workspace)->create();
        $campaign = Campaign::factory()->for($this->workspace)->create(['status' => 'sending']);
        for ($i = 0; $i < $sent; $i++) {
            $delivery = Delivery::create(['campaign_id' => $campaign->id, 'workspace_id' => $this->workspace->id, 'email' => "r{$i}@example.com", 'status' => 'sent']);
            $delivery->forceFill(['sent_at' => now()->subDay()])->save();
            $event = match (true) {
                $i < $hardBounces => ['type' => 'bounce', 'subtype' => 'hard'],
                $i < $hardBounces + $softBounces => ['type' => 'bounce', 'subtype' => 'soft'],
                $i < $hardBounces + $softBounces + $complaints => ['type' => 'complaint'],
                default => null,
            };
            if ($event) {
                $delivery->events()->create($event + ['campaign_id' => $campaign->id]);
            }
        }

        return $campaign;
    }

    public function test_health_measures_hard_bounces_and_complaints_only(): void
    {
        Setting::put(['health_min_sent' => 10]);
        $this->seedTraffic(sent: 20, hardBounces: 1, softBounces: 5);

        $health = SendingHealth::measure($this->workspace);

        $this->assertSame(20, $health['sent']);
        $this->assertSame(1, $health['bounces']);
        $this->assertSame(5.0, $health['bounce_rate']);
        $this->assertSame('warn', $health['level']);
    }

    public function test_small_senders_are_not_rated(): void
    {
        $this->seedTraffic(sent: 10, hardBounces: 5);

        $this->assertSame('ok', SendingHealth::measure($this->workspace)['level']);
    }

    public function test_monitor_mode_flags_but_does_not_suspend(): void
    {
        Setting::put(['health_mode' => 'monitor', 'health_min_sent' => 10]);
        $this->seedTraffic(sent: 10, hardBounces: 2);

        $this->artisan('health:check')->assertSuccessful();

        $this->assertFalse($this->workspace->fresh()->isSuspended());
        $this->assertSame('suspend', SendingHealth::measure($this->workspace)['level']);
    }

    public function test_enforce_mode_suspends_pauses_campaigns_and_blocks_launch_until_lifted(): void
    {
        Setting::put(['health_mode' => 'enforce', 'health_min_sent' => 10]);
        $running = $this->seedTraffic(sent: 10, hardBounces: 0, complaints: 1);

        $this->artisan('health:check')->assertSuccessful();

        $this->workspace->refresh();
        $this->assertTrue($this->workspace->isSuspended());
        $this->assertStringContainsString('Complaint rate', $this->workspace->suspension_reason);
        $this->assertSame('paused', $running->fresh()->status);

        SendingServer::factory()->for($this->workspace)->create();
        $list = MailList::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($list, 'list')->create();
        $draft = Campaign::factory()->for($this->workspace)->create();
        $draft->audiences()->create(['mail_list_id' => $list->id]);
        $this->assertFalse(app(CampaignService::class)->canLaunch($draft->load('audiences.segment')));

        $this->signInTo($this->workspace, 'admin');
        $this->get(route('dashboard'))->assertSee('Sending is suspended');
        $this->post(route('settings.health.lift', $this->workspace))->assertRedirect();

        $this->workspace->refresh();
        $this->assertFalse($this->workspace->isSuspended());
        // Old events no longer count after the review
        $this->assertSame(0, SendingHealth::measure($this->workspace)['sent']);
    }

    public function test_health_limits_are_validated(): void
    {
        $this->signInTo($this->workspace, 'admin');

        $this->put(route('settings.health'), [
            'health_mode' => 'enforce', 'health_bounce_warn' => 10, 'health_bounce_suspend' => 5,
            'health_complaint_warn' => 0.1, 'health_complaint_suspend' => 0.3, 'health_window_days' => 7, 'health_min_sent' => 100,
        ])->assertSessionHasErrors('health_bounce_suspend');
    }
}

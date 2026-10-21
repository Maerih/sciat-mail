<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Segment;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\VerificationServer;
use App\Models\Workspace;
use App\Services\CampaignService;
use App\Services\Dns\DnsResolver;
use App\Services\Verification\EmailVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $this->list = MailList::factory()->for($this->workspace)->create();
        // Only example.com has a mail server in this fake DNS
        $this->app->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function txt(string $host): array
            {
                return [];
            }

            public function cname(string $host): array
            {
                return [];
            }

            public function mx(string $domain): bool
            {
                return $domain === 'example.com';
            }
        });
    }

    public function test_builtin_check_flags_syntax_dns_disposable_and_role_addresses(): void
    {
        $verifier = app(EmailVerifier::class);
        $builtin = VerificationServer::activeFor($this->workspace);

        $this->assertSame('builtin', $builtin->provider);
        $this->assertSame(['deliverable', null], $verifier->verify('jane@example.com', $builtin));
        $this->assertSame('risky', $verifier->verify('info@example.com', $builtin)[0]);
        $this->assertSame(['undeliverable', 'Domain has no mail server (MX)'], $verifier->verify('jane@no-mail.test', $builtin));
        $this->assertSame('undeliverable', $verifier->verify('x@mailinator.com', $builtin)[0]);
        $this->assertSame(['undeliverable', 'Invalid syntax'], $verifier->verify('not an email', $builtin));
    }

    public function test_provider_responses_are_normalised(): void
    {
        Http::fake([
            'api.emailable.com/*' => Http::response(['state' => 'undeliverable', 'reason' => 'rejected_email']),
            'api.kickbox.com/*' => Http::response(['success' => true, 'result' => 'risky', 'reason' => 'low_quality']),
            'api.zerobounce.net/*' => Http::response(['status' => 'catch-all', 'sub_status' => '']),
        ]);
        $verifier = app(EmailVerifier::class);
        $server = fn ($provider) => new VerificationServer(['provider' => $provider, 'api_key' => 'key-123']);

        $this->assertSame(['undeliverable', 'rejected_email'], $verifier->verify('a@example.com', $server('emailable')));
        $this->assertSame(['risky', 'low_quality'], $verifier->verify('a@example.com', $server('kickbox')));
        $this->assertSame(['risky', 'catch-all'], $verifier->verify('a@example.com', $server('zerobounce')));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.emailable.com') && $request['api_key'] === 'key-123');
    }

    public function test_provider_errors_yield_unknown_instead_of_failing(): void
    {
        Http::fake(['api.emailable.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        [$status] = app(EmailVerifier::class)->verify('a@example.com', new VerificationServer(['provider' => 'emailable', 'api_key' => 'bad']));

        $this->assertSame('unknown', $status);
    }

    public function test_verify_list_checks_unverified_contacts_and_campaigns_skip_undeliverable(): void
    {
        $this->signInTo($this->workspace);
        SendingServer::factory()->for($this->workspace)->create();
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'good@example.com']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'bad@no-mail.test']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'kept@no-mail.test', 'verification_status' => 'deliverable']);

        $this->post(route('lists.verify', $this->list))->assertSessionHas('success');

        $statuses = $this->list->subscribers()->pluck('verification_status', 'email')->all();
        $this->assertSame(['bad@no-mail.test' => 'undeliverable', 'good@example.com' => 'deliverable', 'kept@no-mail.test' => 'deliverable'], collect($statuses)->sortKeys()->all());
        $this->assertSame('done', $this->list->fresh()->verification_job_status);

        $campaign = Campaign::factory()->for($this->workspace)->create();
        $campaign->audiences()->create(['mail_list_id' => $this->list->id]);
        app(CampaignService::class)->launch($campaign);
        $this->assertEqualsCanonicalizing(['good@example.com', 'kept@no-mail.test'], $this->transport->recipients());
    }

    public function test_bulk_verify_rechecks_selected_contacts(): void
    {
        $this->signInTo($this->workspace);
        $subscriber = Subscriber::factory()->for($this->list, 'list')->create(['email' => 'old@no-mail.test', 'verification_status' => 'deliverable']);

        $this->post(route('lists.subscribers.bulk', $this->list), ['action' => 'verify', 'scope' => 'selected', 'ids' => [$subscriber->uid]])
            ->assertSessionHas('success');

        $this->assertSame('undeliverable', $subscriber->fresh()->verification_status);
        $this->assertNotNull($subscriber->fresh()->verified_at);
    }

    public function test_segment_and_list_filter_by_verification_result(): void
    {
        $this->signInTo($this->workspace);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'risky@example.com', 'verification_status' => 'risky']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'fine@example.com', 'verification_status' => 'deliverable']);

        $matches = Segment::applyConditions($this->list->subscribers()->getQuery(), [['field' => 'verification_status', 'operator' => 'equal', 'value' => 'risky']])->pluck('email');
        $this->assertSame(['risky@example.com'], $matches->all());

        $this->get(route('lists.subscribers.index', [$this->list, 'verification' => 'risky']))
            ->assertOk()->assertSee('risky@example.com')->assertDontSee('fine@example.com');
    }

    public function test_api_key_is_required_for_paid_providers_and_stored_encrypted(): void
    {
        $this->signInTo($this->workspace);

        $this->post(route('sending.verification.store'), ['name' => 'Emailable', 'provider' => 'emailable'])->assertSessionHasErrors('api_key');
        $this->post(route('sending.verification.store'), ['name' => 'Emailable', 'provider' => 'emailable', 'api_key' => 'live_secret_key'])->assertRedirect();

        $server = VerificationServer::first();
        $this->assertTrue($server->is_default);
        $this->assertStringNotContainsString('live_secret_key', $server->getRawOriginal('api_key'));
        $this->get(route('sending.verification.index'))->assertOk()->assertDontSee('live_secret_key');
        $this->assertSame('emailable', VerificationServer::activeFor($this->workspace)->provider);
    }
}

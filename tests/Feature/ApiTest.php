<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\ApiToken;
use App\Models\Automation;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Template;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['contact_email' => 'hello@client.com']);
        $this->list = MailList::factory()->for($this->workspace)->create();
        SendingServer::factory()->for($this->workspace)->create();
        [, $this->token] = ApiToken::issue($this->workspace, 'Tests');
    }

    protected function api(): static
    {
        return $this->withToken($this->token)->withHeader('Accept', 'application/json');
    }

    public function test_requests_need_a_valid_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken('sm_wrong')->getJson('/api/v1/me')->assertUnauthorized();

        $this->api()->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.client.uid', $this->workspace->uid);
        $this->assertNotNull(ApiToken::first()->last_used_at);
        $this->assertNotSame($this->token, ApiToken::first()->token_hash, 'only a hash is stored');
    }

    public function test_token_cannot_reach_another_clients_data(): void
    {
        $foreign = MailList::factory()->create();
        Subscriber::factory()->for($foreign, 'list')->create(['email' => 'secret@other.com']);

        $this->api()->getJson("/api/v1/lists/{$foreign->uid}")->assertNotFound();
        $this->api()->getJson("/api/v1/lists/{$foreign->uid}/subscribers")->assertNotFound();
        $this->api()->getJson('/api/v1/lists')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uid', $this->list->uid);
    }

    public function test_list_crud(): void
    {
        $uid = $this->api()->postJson('/api/v1/lists', ['name' => 'Customers', 'from_name' => 'Acme', 'from_email' => 'news@acme.com'])
            ->assertCreated()->assertJsonPath('data.fields.0.tag', 'FIRST_NAME')->json('data.uid');

        $this->api()->patchJson("/api/v1/lists/{$uid}", ['name' => 'VIP'])->assertOk()->assertJsonPath('data.name', 'VIP');
        $this->api()->getJson("/api/v1/lists/{$uid}")->assertOk()->assertJsonPath('data.subscriber_counts.subscribed', 0);
        $this->api()->postJson('/api/v1/lists', ['name' => 'Bad'])->assertUnprocessable()->assertJsonValidationErrors(['from_name', 'from_email']);
        $this->api()->deleteJson("/api/v1/lists/{$uid}")->assertNoContent();
    }

    public function test_contacts_are_upserted_by_email_with_known_fields_only(): void
    {
        $url = "/api/v1/lists/{$this->list->uid}/subscribers";

        $this->api()->postJson($url, ['email' => 'Ann@Example.com', 'fields' => ['first_name' => 'Ann', 'HACK' => 'x'], 'tags' => ['shop']])
            ->assertCreated()->assertJsonPath('data.email', 'ann@example.com')->assertJsonPath('data.fields.FIRST_NAME', 'Ann')
            ->assertJsonMissingPath('data.fields.HACK')->assertJsonPath('confirmation_sent', false);

        $this->api()->postJson($url, ['email' => 'ann@example.com', 'fields' => ['LAST_NAME' => 'Lee']])
            ->assertOk()->assertJsonPath('data.fields.FIRST_NAME', 'Ann')->assertJsonPath('data.fields.LAST_NAME', 'Lee');

        $this->assertSame(1, $this->list->subscribers()->count());
    }

    public function test_double_opt_in_lists_send_confirmation_unless_confirmed(): void
    {
        $this->list->update(['double_optin' => true]);
        $url = "/api/v1/lists/{$this->list->uid}/subscribers";

        $this->api()->postJson($url, ['email' => 'new@example.com'])->assertJsonPath('data.status', 'unconfirmed')->assertJsonPath('confirmation_sent', true);
        $this->api()->postJson($url, ['email' => 'consented@example.com', 'confirmed' => true])->assertJsonPath('data.status', 'subscribed');
    }

    public function test_contact_lookup_update_tags_unsubscribe_and_delete(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'bob@example.com', 'tags' => ['lead']]);
        $base = "/api/v1/lists/{$this->list->uid}/subscribers/bob@example.com";

        $this->api()->getJson($base)->assertOk()->assertJsonPath('data.tags', ['lead']);
        $this->api()->postJson("{$base}/tags", ['add' => ['customer'], 'remove' => ['lead']])->assertJsonPath('data.tags', ['customer']);
        $this->api()->patchJson($base, ['fields' => ['FIRST_NAME' => 'Bob']])->assertJsonPath('data.fields.FIRST_NAME', 'Bob');
        $this->api()->postJson("{$base}/unsubscribe")->assertJsonPath('data.status', 'unsubscribed');
        $this->api()->deleteJson($base)->assertNoContent();
        $this->api()->getJson($base)->assertNotFound();
    }

    public function test_bulk_import_reports_created_updated_and_failed(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'old@example.com']);

        $this->api()->postJson("/api/v1/lists/{$this->list->uid}/subscribers/bulk", ['subscribers' => [
            ['email' => 'old@example.com'], ['email' => 'new@example.com', 'fields' => ['FIRST_NAME' => 'N']], ['email' => 'broken'],
        ]])->assertOk()->assertJson(['created' => 1, 'updated' => 1, 'failed' => [['index' => 2, 'email' => 'broken', 'error' => 'Invalid email']]]);
    }

    public function test_campaign_can_be_created_and_launched_through_the_api(): void
    {
        Subscriber::factory()->for($this->list, 'list')->count(3)->create();

        $uid = $this->api()->postJson('/api/v1/campaigns', [
            'name' => 'API news', 'subject' => 'Hello', 'from_name' => 'Acme', 'from_email' => 'news@acme.com',
            'html' => '<p>Hi</p>', 'lists' => [$this->list->uid], 'send' => true,
        ])->assertCreated()->json('data.uid');

        $this->assertCount(3, $this->transport->sent);
        $this->api()->getJson("/api/v1/campaigns/{$uid}")->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.stats.sent', 3);
        $this->api()->postJson('/api/v1/campaigns', [
            'name' => 'x', 'subject' => 'x', 'from_name' => 'x', 'from_email' => 'x@x.com', 'html' => 'x', 'lists' => ['nope'],
        ])->assertUnprocessable()->assertJsonValidationErrors('lists');
    }

    public function test_api_triggered_automation_enrolls_contacts(): void
    {
        $automation = Automation::create(['workspace_id' => $this->workspace->id, 'mail_list_id' => $this->list->id, 'name' => 'After purchase',
            'trigger_type' => 'api', 'status' => 'active']);
        $email = Campaign::factory()->for($this->workspace)->create(['automation_id' => $automation->id, 'status' => 'automation', 'subject' => 'Thanks for your order']);
        $flow = $automation->flow();
        $flow->insert(null, 'next', ['type' => 'send', 'campaign_id' => $email->id]);
        $automation->setFlow($flow);
        $automation->save();
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'buyer@example.com']);

        $this->api()->postJson("/api/v1/automations/{$automation->uid}/enroll", ['email' => 'buyer@example.com'])->assertCreated()->assertJson(['enrolled' => true]);
        $this->api()->postJson("/api/v1/automations/{$automation->uid}/enroll", ['email' => 'buyer@example.com'])->assertOk()->assertJson(['enrolled' => false]);
        $this->api()->postJson("/api/v1/automations/{$automation->uid}/enroll", ['email' => 'stranger@example.com'])->assertNotFound();

        $this->assertSame('Thanks for your order', $this->transport->sent[0]->getOriginalMessage()->getSubject());
    }

    public function test_transactional_send_fills_template_data(): void
    {
        $template = Template::create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt', 'html' => '<p>Order {{ ORDER }} for {{ NAME }}</p>']);

        $id = $this->api()->postJson('/api/v1/send', [
            'to' => 'buyer@example.com', 'subject' => 'Receipt #{{ ORDER }}', 'template' => $template->uid, 'data' => ['order' => '1042', 'name' => '<b>Ann</b>'],
        ])->assertStatus(202)->json('id');

        $message = $this->transport->sent[0]->getOriginalMessage();
        $this->assertSame('Receipt #1042', $message->getSubject());
        $this->assertStringContainsString('Order 1042 for &lt;b&gt;Ann&lt;/b&gt;', $message->getHtmlBody());
        $this->assertSame('hello@client.com', $message->getFrom()[0]->getAddress());
        $this->assertDatabaseHas('deliveries', ['uid' => $id, 'kind' => 'transactional', 'status' => 'sent']);
    }

    public function test_events_are_delivered_to_webhooks_with_a_valid_signature(): void
    {
        Http::fake(['hooks.client.test/*' => Http::response('ok', 200)]);
        $hook = Webhook::create(['workspace_id' => $this->workspace->id, 'url' => 'https://hooks.client.test/email', 'events' => ['subscriber.subscribed']]);

        $this->api()->postJson("/api/v1/lists/{$this->list->uid}/subscribers", ['email' => 'hooked@example.com'])->assertCreated();

        Http::assertSent(function ($request) use ($hook) {
            return $request->url() === 'https://hooks.client.test/email'
                && $request->header('X-SM-Event')[0] === 'subscriber.subscribed'
                && $request->header('X-SM-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), $hook->secret)
                && $request['data']['email'] === 'hooked@example.com';
        });
        $this->assertSame('success', WebhookDelivery::first()->status);
    }

    public function test_failing_webhook_is_retried_then_marked_failed(): void
    {
        Http::fake(['hooks.client.test/*' => Http::response('boom', 500)]);
        $hook = Webhook::create(['workspace_id' => $this->workspace->id, 'url' => 'https://hooks.client.test/x', 'events' => ['subscriber.unsubscribed']]);
        Subscriber::factory()->for($this->list, 'list')->create()->unsubscribe();

        $delivery = WebhookDelivery::first();
        $this->assertSame('pending', $delivery->status, 'first failure is retried later');
        $this->assertSame(500, $delivery->response_status);

        // last allowed attempt
        $job = (new DeliverWebhook($delivery->id))->withFakeQueueInteractions();
        $job->job->attempts = 5;
        $job->handle();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(1, $hook->fresh()->failures);
    }

    public function test_token_and_webhook_management_screens(): void
    {
        $this->signInTo($this->workspace);

        $this->post(route('api.tokens.store'), ['name' => 'Shop'])->assertSessionHas('new_token');
        $plain = session('new_token');
        $this->assertStringStartsWith('sm_', $plain);
        $this->get(route('api.settings'))->assertOk()->assertSee('Shop');
        $this->get(route('api.docs'))->assertOk()->assertSee('/lists/'.$this->list->uid.'/subscribers', false);

        $this->post(route('api.webhooks.store'), ['url' => 'https://hooks.client.test/a', 'events' => ['nope']])->assertSessionHasErrors('events.0');
        $this->post(route('api.webhooks.store'), ['url' => 'https://hooks.client.test/a', 'events' => ['email.opened']])->assertRedirect();
        $this->assertSame(['email.opened'], Webhook::first()->events);

        $token = ApiToken::where('name', 'Shop')->first();
        $this->delete(route('api.tokens.destroy', $token->id))->assertRedirect();
        $this->withToken($plain)->getJson('/api/v1/me')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Automation;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Automation\Engine;
use App\Sms\PhoneNumber;
use App\Sms\SmsManager;
use App\Sms\SmsService;
use App\Sms\SmsText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['timezone' => 'Africa/Nairobi']);
        $this->list = MailList::factory()->for($this->workspace)->create();
        $this->list->fields()->create(['label' => 'Phone', 'tag' => 'PHONE', 'type' => 'phone', 'sort' => 9]);
    }

    protected function provider(string $type = 'log', array $config = [], ?Workspace $workspace = null): SmsProvider
    {
        return SmsProvider::create(['workspace_id' => ($workspace ?? $this->workspace)->id, 'name' => ucfirst($type), 'type' => $type, 'config' => $config, 'sender_id' => 'SCIAT', 'status' => 'active', 'per_second' => 100]);
    }

    protected function contact(string $phone, array $attributes = []): Subscriber
    {
        return Subscriber::factory()->for($this->list, 'list')->create($attributes + ['data' => ['FIRST_NAME' => 'Wanjiku', 'PHONE' => $phone]]);
    }

    public function test_phone_numbers_are_normalised_to_e164(): void
    {
        $this->assertSame('+254712345678', PhoneNumber::normalize('0712 345 678', 'KE'));
        $this->assertSame('+254712345678', PhoneNumber::normalize('712345678', 'KE'));
        $this->assertSame('+254712345678', PhoneNumber::normalize('254712345678', 'KE'));
        $this->assertSame('+254712345678', PhoneNumber::normalize('+254 (712) 345-678', 'KE'));
        $this->assertSame('+254110345678', PhoneNumber::normalize('0110345678', 'KE'));
        $this->assertSame('+256772123456', PhoneNumber::normalize('0772 123456', 'UG'));
        $this->assertSame('+14155552671', PhoneNumber::normalize('00 1 415 555 2671', 'KE'));
        $this->assertNull(PhoneNumber::normalize('12345', 'KE'));
        $this->assertNull(PhoneNumber::normalize('', 'KE'));
        $this->assertSame('0712345678', PhoneNumber::local('+254712345678', 'KE'));
    }

    public function test_message_length_follows_gsm_and_unicode_rules(): void
    {
        $this->assertSame(1, SmsText::parts(str_repeat('a', 160)));
        $this->assertSame(2, SmsText::parts(str_repeat('a', 161)));
        $this->assertSame(2, SmsText::parts(str_repeat('a', 306)));
        $this->assertSame(3, SmsText::parts(str_repeat('a', 307)));
        $this->assertFalse(SmsText::isGsm('Hello 👋'));
        $this->assertSame(1, SmsText::parts(str_repeat('ü', 160)), 'ü is in the GSM alphabet');
        $this->assertSame(2, SmsText::parts('👋'.str_repeat('a', 70)));
        $this->assertSame(2, SmsText::length('€'), 'extended GSM characters count twice');
    }

    public function test_subscriber_phone_is_kept_in_sync_from_the_list_field(): void
    {
        $subscriber = $this->contact('0712 345 678');
        $this->assertSame('+254712345678', $subscriber->phone);

        $subscriber->update(['data' => ['PHONE' => 'not a number']]);
        $this->assertNull($subscriber->fresh()->phone);
    }

    public function test_africas_talking_driver_sends_and_reads_callbacks(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::sequence()
            ->push(['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [
                ['statusCode' => 101, 'number' => '+254712345678', 'status' => 'Success', 'cost' => 'KES 0.8000', 'messageId' => 'ATXid_1'],
            ]]])
            ->push(['SMSMessageData' => ['Recipients' => [['statusCode' => 405, 'status' => 'InsufficientBalance']]]])]);
        $provider = $this->provider('africastalking', ['username' => 'sciat', 'api_key' => 'k']);

        $result = SmsManager::driver('africastalking')->send($provider, '+254712345678', 'Habari', 'SCIAT');

        $this->assertTrue($result->ok);
        $this->assertSame('ATXid_1', $result->messageId);
        Http::assertSent(fn ($r) => $r->hasHeader('apiKey', 'k') && $r['username'] === 'sciat' && $r['to'] === '+254712345678' && $r['from'] === 'SCIAT');

        $this->assertSame('InsufficientBalance', SmsManager::driver('africastalking')->send($provider, '+254712345678', 'x', null)->error);
    }

    public function test_twilio_infobip_and_vonage_drivers(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
            'xyz.api.infobip.com/*' => Http::response(['messages' => [['messageId' => 'IB1', 'status' => ['groupName' => 'PENDING']]]]),
            'rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0', 'message-id' => 'VN1']]]),
        ]);

        $twilio = $this->provider('twilio', ['account_sid' => 'AC1', 'auth_token' => 't']);
        $this->assertSame('SM123', SmsManager::driver('twilio')->send($twilio, '+254712345678', 'Hi', 'SCIAT')->messageId);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/Accounts/AC1/Messages.json') && $r['To'] === '+254712345678' && str_contains($r['StatusCallback'], '/webhooks/sms/'));

        $infobip = $this->provider('infobip', ['base_url' => 'https://xyz.api.infobip.com/', 'api_key' => 'ib']);
        $this->assertSame('IB1', SmsManager::driver('infobip')->send($infobip, '+254712345678', 'Hi', 'SCIAT')->messageId);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'App ib') && $r['messages'][0]['destinations'][0]['to'] === '254712345678');

        $vonage = $this->provider('vonage', ['api_key' => 'v', 'api_secret' => 's']);
        $this->assertSame('VN1', SmsManager::driver('vonage')->send($vonage, '+254712345678', 'Hi', 'SCIAT')->messageId);
    }

    public function test_custom_http_provider_fills_placeholders(): void
    {
        Http::fake(['sms.example.co.ke/*' => Http::response(['responses' => [['respose-code' => 200, 'messageid' => 'C77']]])]);
        $provider = $this->provider('http', [
            'url' => 'https://sms.example.co.ke/api/services/sendsms/', 'method' => 'post_json',
            'params' => "apikey=secret\nmobile={to_plain}\nlocal={to_local}\nmessage={message}\nshortcode={sender}",
            'headers' => '', 'success_contains' => '', 'message_id_path' => 'responses.0.messageid',
        ]);

        $result = SmsManager::driver('http')->send($provider, '+254712345678', 'Hello there', 'SCIAT');

        $this->assertSame('C77', $result->messageId);
        Http::assertSent(fn ($r) => $r['mobile'] === '254712345678' && $r['local'] === '0712345678' && $r['message'] === 'Hello there' && $r['shortcode'] === 'SCIAT' && $r['apikey'] === 'secret');
    }

    public function test_campaign_sends_personalised_messages_once_per_number_and_skips_opted_out(): void
    {
        $this->provider();
        $this->contact('0712345678');
        $this->contact('+254712345678'); // same number on another contact → one message
        $this->contact('0722000111', ['sms_opted_out_at' => now()]);
        $this->contact('0733000222', ['status' => 'unsubscribed']);
        Subscriber::factory()->for($this->list, 'list')->create(['data' => ['FIRST_NAME' => 'No phone']]);
        $this->signInTo($this->workspace);

        $this->post(route('sms.campaigns.store'))->assertRedirect();
        $campaign = SmsCampaign::sole();
        $this->put(route('sms.campaigns.update', $campaign), [
            'name' => 'Flash sale', 'message' => 'Hi {{ SUBSCRIBER_FIRST_NAME }}, 20% off today!', 'opt_out_text' => 'Reply STOP to opt out',
            'audience_type' => 'list', 'list_id' => $this->list->id, 'next' => 'launch',
        ])->assertRedirect(route('sms.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertSame('done', $campaign->status);
        $message = $campaign->messages()->sole();
        $this->assertSame('+254712345678', $message->phone);
        $this->assertSame("Hi Wanjiku, 20% off today!\nReply STOP to opt out", $message->body);
        $this->assertSame('delivered', $message->status);
        $this->get(route('sms.campaigns.show', $campaign))->assertOk()->assertSee('+254712345678');
    }

    public function test_monthly_sms_limit_caps_a_campaign(): void
    {
        $this->workspace->forceFill(['monthly_sms_limit' => 1])->save();
        $this->provider();
        $this->contact('0712000001');
        $this->contact('0712000002');
        $campaign = new SmsCampaign(['name' => 'X', 'message' => 'Hello', 'audience' => ['type' => 'list', 'list_id' => $this->list->id]]);
        $campaign->workspace_id = $this->workspace->id;
        $campaign->save();

        app(SmsService::class)->launch($campaign);

        $this->assertSame(1, $campaign->messages()->count());
    }

    public function test_email_to_sms_targets_people_who_did_not_open(): void
    {
        $opened = $this->contact('0712000001');
        $ignored = $this->contact('0712000002');
        $email = Campaign::factory()->for($this->workspace)->create(['status' => 'done']);
        foreach ([[$opened, now()], [$ignored, null]] as [$subscriber, $openedAt]) {
            $delivery = Delivery::create(['campaign_id' => $email->id, 'subscriber_id' => $subscriber->id, 'workspace_id' => $this->workspace->id, 'email' => $subscriber->email, 'status' => 'sent']);
            $delivery->forceFill(['sent_at' => now()->subHour(), 'opened_at' => $openedAt])->save();
        }
        $campaign = new SmsCampaign(['name' => 'Follow up', 'message' => 'Did you see our email?', 'audience' => ['type' => 'non_openers', 'campaign_id' => $email->id]]);
        $campaign->workspace_id = $this->workspace->id;
        $campaign->save();

        $this->assertSame([$ignored->id], $campaign->recipientQuery()->pluck('id')->all());
    }

    public function test_delivery_reports_and_replies_with_stop_opt_out(): void
    {
        $provider = $this->provider('africastalking', ['username' => 'sciat', 'api_key' => 'k']);
        $subscriber = $this->contact('0712345678');
        $sent = SmsMessage::create(['workspace_id' => $this->workspace->id, 'sms_provider_id' => $provider->id, 'subscriber_id' => $subscriber->id,
            'phone' => '+254712345678', 'body' => 'Hi', 'status' => 'sent', 'provider_message_id' => 'ATXid_9']);

        $this->post($provider->webhookUrl('status'), ['id' => 'ATXid_9', 'status' => 'Success', 'phoneNumber' => '+254712345678'])->assertOk();
        $this->assertSame('delivered', $sent->fresh()->status);

        $this->post(route('webhooks.sms', [$provider->uid, 'wrong-secret', 'status']), ['id' => 'x'])->assertNotFound();

        $this->post($provider->webhookUrl('inbound'), ['from' => '+254712345678', 'to' => '22384', 'text' => 'Stop', 'id' => 'in1'])->assertOk();
        $this->assertNotNull($subscriber->fresh()->sms_opted_out_at);
        $this->assertSame('received', SmsMessage::where('direction', 'in')->sole()->status);

        $this->post($provider->webhookUrl('inbound'), ['from' => '0712345678', 'text' => 'START'])->assertOk();
        $this->assertNull($subscriber->fresh()->sms_opted_out_at);
    }

    public function test_twilio_inbound_gets_twiml_and_infobip_reports_are_parsed(): void
    {
        $twilio = $this->provider('twilio', ['account_sid' => 'AC1', 'auth_token' => 't']);
        $this->post($twilio->webhookUrl('inbound'), ['From' => '+254712345678', 'To' => '+1555', 'Body' => 'Hello', 'MessageSid' => 'SMin'])
            ->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertSame('Hello', SmsMessage::where('direction', 'in')->sole()->body);

        $infobip = $this->provider('infobip', ['base_url' => 'x.api.infobip.com', 'api_key' => 'k']);
        $message = SmsMessage::create(['workspace_id' => $this->workspace->id, 'sms_provider_id' => $infobip->id, 'phone' => '+254712345678', 'body' => 'x', 'status' => 'sent', 'provider_message_id' => 'IB5']);
        $this->postJson($infobip->webhookUrl('status'), ['results' => [['messageId' => 'IB5', 'to' => '254712345678', 'status' => ['groupName' => 'UNDELIVERABLE'], 'error' => ['groupName' => 'HANDSET_ERRORS', 'description' => 'Absent subscriber']]]])->assertOk();
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('Absent subscriber', $message->fresh()->error);
    }

    public function test_inbox_shows_threads_and_sends_replies(): void
    {
        $provider = $this->provider();
        $subscriber = $this->contact('0712345678');
        $this->post($provider->webhookUrl('inbound'), []); // log driver ignores webhooks
        app(SmsService::class)->receive($provider, ['from' => '+254712345678', 'to' => null, 'text' => 'When is the sale?', 'id' => null]);
        $this->signInTo($this->workspace);

        $this->get(route('sms.inbox'))->assertOk()->assertSee('When is the sale?')->assertSee($subscriber->email);
        $this->post(route('sms.inbox.reply'), ['phone' => '+254712345678', 'message' => 'Tomorrow at 9am'])->assertRedirect();

        $reply = SmsMessage::where('direction', 'out')->sole();
        $this->assertSame('Tomorrow at 9am', $reply->body);
        $this->assertSame('reply', $reply->source);
        $this->assertSame('delivered', $reply->status);
    }

    public function test_automation_sms_step_texts_the_contact(): void
    {
        $this->provider();
        $subscriber = $this->contact('0712345678');
        $automation = Automation::create(['workspace_id' => $this->workspace->id, 'mail_list_id' => $this->list->id, 'name' => 'Welcome', 'trigger_type' => 'subscribed', 'status' => 'active']);
        $flow = $automation->flow();
        $flow->insert(null, 'next', ['type' => 'sms', 'message' => 'Karibu {{ SUBSCRIBER_FIRST_NAME }}!']);
        $automation->setFlow($flow);
        $automation->save();

        app(Engine::class)->enroll($automation, $subscriber);

        $message = SmsMessage::sole();
        $this->assertSame('Karibu Wanjiku!', $message->body);
        $this->assertSame('automation', $message->source);
    }

    public function test_sms_api_sends_and_reports_status(): void
    {
        $this->provider();
        [, $token] = ApiToken::issue($this->workspace, 'App');

        $response = $this->withToken($token)->postJson('/api/v1/sms', ['to' => '0712345678', 'message' => 'Your code is 4821'])->assertAccepted();
        $id = $response->json('data.id');
        $this->assertSame('+254712345678', $response->json('data.to'));

        $this->withToken($token)->getJson('/api/v1/sms/'.$id)->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->withToken($token)->postJson('/api/v1/sms', ['to' => '123', 'message' => 'x'])->assertUnprocessable();
    }

    public function test_provider_pages_secrets_and_isolation(): void
    {
        $this->signInTo($this->workspace);
        $this->get(route('sms.providers.index'))->assertOk()->assertSee("Africa's Talking");
        $this->get(route('sms.providers.create', ['type' => 'africastalking']))->assertOk();
        $this->post(route('sms.providers.store'), ['type' => 'africastalking', 'name' => 'AT', 'status' => 'active', 'per_second' => 20, 'sender_id' => 'SCIAT',
            'config' => ['username' => 'sciat', 'api_key' => 'super-secret-key']])->assertRedirect();

        $provider = SmsProvider::sole();
        $this->assertTrue($provider->is_default);
        $this->assertStringNotContainsString('super-secret-key', (string) \DB::table('sms_providers')->value('config'), 'config is encrypted at rest');
        $this->get(route('sms.providers.edit', $provider))->assertOk()->assertDontSee('super-secret-key')->assertSee('/webhooks/sms/');

        $this->put(route('sms.providers.update', $provider), ['name' => 'AT', 'status' => 'active', 'per_second' => 20, 'config' => ['username' => 'sciat', 'api_key' => '']]);
        $this->assertSame('super-secret-key', $provider->fresh()->configValue('api_key'), 'blank secret keeps the stored one');

        $this->signInTo(Workspace::factory()->create());
        $this->get(route('sms.providers.edit', $provider))->assertNotFound();
    }

    public function test_sms_pages_render(): void
    {
        $this->provider();
        $this->signInTo($this->workspace);
        $this->get(route('sms.campaigns.index'))->assertOk()->assertSee('Reach people instantly by SMS');
        $this->post(route('sms.campaigns.store'));
        $this->get(route('sms.campaigns.edit', SmsCampaign::sole()))->assertOk()->assertSee('Merge tags');
        $this->get(route('sms.inbox'))->assertOk();
    }
}

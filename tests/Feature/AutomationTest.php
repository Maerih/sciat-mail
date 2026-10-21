<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Automation\Engine;
use App\Services\Automation\Flow;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutomationTest extends TestCase
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

    /**
     * Builds an active automation. $steps is a linear list of step definitions; a 'send'
     * entry may carry 'subject'. Returns the automation and the created step ids.
     *
     * @return array{0: Automation, 1: string[]}
     */
    protected function automation(string $trigger, array $steps, array $config = [], array $attributes = []): array
    {
        $automation = Automation::create($attributes + [
            'workspace_id' => $this->workspace->id, 'mail_list_id' => $this->list->id, 'name' => 'Flow',
            'trigger_type' => $trigger, 'trigger_config' => $config, 'status' => 'active',
        ]);

        $flow = $automation->flow();
        $ids = [];
        $parent = null;
        foreach ($steps as $step) {
            if ($step['type'] === 'send') {
                $email = Campaign::factory()->for($this->workspace)->create([
                    'automation_id' => $automation->id, 'subject' => $step['subject'] ?? 'Email', 'status' => 'automation',
                ]);
                $step = ['type' => 'send', 'campaign_id' => $email->id];
            }
            $parent = $flow->insert($parent, 'next', $step);
            $ids[] = $parent;
        }
        $automation->setFlow($flow);
        $automation->save();

        return [$automation, $ids];
    }

    /** @return string[] */
    protected function subjects(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage()->getSubject(), $this->transport->sent);
    }

    public function test_welcome_series_sends_first_email_now_and_second_after_the_wait(): void
    {
        [$automation] = $this->automation('subscribed', [
            ['type' => 'send', 'subject' => 'Welcome!'],
            ['type' => 'wait', 'amount' => 2, 'unit' => 'days'],
            ['type' => 'send', 'subject' => 'Getting started'],
        ]);

        app(SubscriptionService::class)->subscribe($this->list, 'new@example.com', [], 'form');

        $this->assertSame(['Welcome!'], $this->subjects());
        $run = $automation->runs()->first();
        $this->assertSame('waiting', $run->status);

        $this->travel(1)->days();
        app(Engine::class)->tick();
        $this->assertCount(1, $this->transport->sent, 'still waiting');

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        app(Engine::class)->tick();
        $this->assertSame(['Welcome!', 'Getting started'], $this->subjects());
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_double_opt_in_contacts_enter_only_after_confirming(): void
    {
        $this->list->update(['double_optin' => true]);
        $this->automation('subscribed', [['type' => 'send', 'subject' => 'Welcome!']]);
        $service = app(SubscriptionService::class);

        [$subscriber] = $service->subscribe($this->list, 'new@example.com', [], 'form');
        $this->assertNotContains('Welcome!', $this->subjects());

        $service->confirm($subscriber->fresh());
        $this->assertContains('Welcome!', $this->subjects());
    }

    public function test_imported_contacts_do_not_trigger_welcome_and_contacts_enter_only_once(): void
    {
        [$automation] = $this->automation('subscribed', [['type' => 'send', 'subject' => 'Welcome!']]);
        Subscriber::factory()->for($this->list, 'list')->create(['source' => 'import']);
        $this->assertSame(0, $automation->runs()->count());

        $subscriber = Subscriber::factory()->for($this->list, 'list')->create();
        $subscriber->unsubscribe();
        $subscriber->forceFill(['status' => 'subscribed'])->save();

        $this->assertSame(1, $automation->runs()->count());
        $this->assertSame(['Welcome!'], $this->subjects());
    }

    public function test_condition_routes_openers_and_non_openers_differently(): void
    {
        [$automation, $ids] = $this->automation('tag_added', [
            ['type' => 'send', 'subject' => 'Offer'],
            ['type' => 'wait', 'amount' => 1, 'unit' => 'days'],
            ['type' => 'condition', 'rule' => ['kind' => 'opened', 'step' => '']],
        ], ['tag' => 'lead']);
        $flow = $automation->flow();
        $flow->insert($ids[2], 'yes', ['type' => 'action', 'action' => 'add_tag', 'tag' => 'engaged']);
        $flow->insert($ids[2], 'no', ['type' => 'send', 'campaign_id' => Campaign::factory()->for($this->workspace)
            ->create(['automation_id' => $automation->id, 'subject' => 'Reminder', 'status' => 'automation'])->id]);
        $automation->setFlow($flow);
        $automation->save();

        $opener = Subscriber::factory()->for($this->list, 'list')->create(['email' => 'opener@example.com']);
        $silent = Subscriber::factory()->for($this->list, 'list')->create(['email' => 'silent@example.com']);
        foreach ([$opener, $silent] as $s) {
            $s->addTags(['lead']);
            $s->save();
        }
        $opener->deliveries()->update(['opened_at' => now()]);

        $this->travel(25)->hours();
        app(Engine::class)->tick();

        $this->assertContains('engaged', $opener->fresh()->tags);
        $this->assertNotContains('engaged', $silent->fresh()->tags ?? []);
        $reminded = collect($this->transport->sent)
            ->filter(fn ($m) => $m->getOriginalMessage()->getSubject() === 'Reminder')
            ->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress())->values()->all();
        $this->assertSame(['silent@example.com'], $reminded);
    }

    public function test_tag_removed_and_field_wildcard_triggers(): void
    {
        [$removed] = $this->automation('tag_removed', [['type' => 'send', 'subject' => 'We miss you']], ['tag' => 'customer']);
        [$field] = $this->automation('field_updated', [['type' => 'send', 'subject' => 'Gold perks']], ['field' => 'PLAN', 'value' => 'gold*']);

        $subscriber = Subscriber::factory()->for($this->list, 'list')->create(['tags' => ['customer'], 'data' => ['PLAN' => 'basic']]);
        $subscriber->removeTags(['customer']);
        $subscriber->data = ['PLAN' => 'silver'];
        $subscriber->save();
        $this->assertSame(1, $removed->runs()->count());
        $this->assertSame(0, $field->runs()->count());

        $subscriber->data = ['PLAN' => 'Gold Annual'];
        $subscriber->save();
        $this->assertSame(1, $field->runs()->count());
    }

    public function test_action_can_start_another_automation(): void
    {
        $this->automation('subscribed', [['type' => 'action', 'action' => 'add_tag', 'tag' => 'onboarded']]);
        [$second] = $this->automation('tag_added', [['type' => 'send', 'subject' => 'Tagged']], ['tag' => 'onboarded']);

        Subscriber::factory()->for($this->list, 'list')->create();

        $this->assertSame(1, $second->runs()->count());
        $this->assertSame(['Tagged'], $this->subjects());
    }

    public function test_contact_who_unsubscribes_mid_journey_exits(): void
    {
        [$automation] = $this->automation('subscribed', [
            ['type' => 'wait', 'amount' => 1, 'unit' => 'hours'],
            ['type' => 'send', 'subject' => 'Too late'],
        ]);
        $subscriber = Subscriber::factory()->for($this->list, 'list')->create();
        $subscriber->unsubscribe();

        $this->travel(2)->hours();
        app(Engine::class)->tick();

        $this->assertSame('exited', $automation->runs()->first()->status);
        $this->assertSame([], $this->transport->sent);
    }

    public function test_goodbye_automation_may_email_someone_who_just_unsubscribed(): void
    {
        $this->automation('unsubscribed', [['type' => 'send', 'subject' => 'Sorry to see you go']]);
        $subscriber = Subscriber::factory()->for($this->list, 'list')->create();

        $subscriber->unsubscribe();

        $this->assertSame(['Sorry to see you go'], $this->subjects());
    }

    public function test_birthday_trigger_fires_once_a_year_at_the_configured_time(): void
    {
        $this->list->fields()->create(['label' => 'Birthday', 'tag' => 'BIRTHDAY', 'type' => 'date', 'sort' => 5]);
        [$automation] = $this->automation('birthday', [['type' => 'send', 'subject' => 'Happy birthday']], ['field' => 'BIRTHDAY', 'time' => '09:00']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'bday@example.com', 'data' => ['BIRTHDAY' => '1990-06-15']]);
        Subscriber::factory()->for($this->list, 'list')->create(['data' => ['BIRTHDAY' => '1990-06-16']]);
        $engine = app(Engine::class);

        $this->travelTo(Carbon::parse('2027-06-15 08:00', 'UTC'));
        $engine->tick();
        $this->assertSame([], $this->transport->sent, 'not before the configured time');

        $this->travelTo(Carbon::parse('2027-06-15 09:05', 'UTC'));
        $engine->tick();
        $engine->tick();
        $this->assertSame(['bday@example.com'], $this->transport->recipients());

        $this->travelTo(Carbon::parse('2028-06-15 09:05', 'UTC'));
        $engine->tick();
        $this->assertCount(2, $this->transport->sent, 'again the next year');
        $this->assertSame(2, $automation->runs()->count());
    }

    public function test_date_field_trigger_honours_offset(): void
    {
        [$automation] = $this->automation('date_field', [['type' => 'send', 'subject' => 'Renewal soon']], ['field' => 'RENEWAL', 'offset_days' => -3, 'time' => '00:00']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'renew@example.com', 'data' => ['RENEWAL' => '2027-03-10']]);

        $this->travelTo(Carbon::parse('2027-03-07 10:00', 'UTC'));
        app(Engine::class)->tick();

        $this->assertSame(['renew@example.com'], $this->transport->recipients());
    }

    public function test_weekly_recurring_automation_resends_every_week(): void
    {
        [$automation] = $this->automation('weekly', [['type' => 'send', 'subject' => 'Weekly digest']], ['weekday' => 1, 'time' => '08:00']);
        Subscriber::factory()->for($this->list, 'list')->count(2)->create();
        Subscriber::factory()->for($this->list, 'list')->status('unsubscribed')->create();
        $engine = app(Engine::class);

        $this->travelTo(Carbon::parse('2027-01-04 08:30', 'UTC')); // Monday
        $engine->tick();
        $engine->tick();
        $this->travelTo(Carbon::parse('2027-01-05 08:30', 'UTC')); // Tuesday
        $engine->tick();
        $this->assertCount(2, $this->transport->sent);

        $this->travelTo(Carbon::parse('2027-01-11 08:30', 'UTC'));
        $engine->tick();
        $this->assertCount(4, $this->transport->sent);
        $this->assertSame(4, $automation->runs()->count());
    }

    public function test_paused_automation_neither_enrolls_nor_advances(): void
    {
        [$automation] = $this->automation('subscribed', [
            ['type' => 'wait', 'amount' => 1, 'unit' => 'hours'],
            ['type' => 'send', 'subject' => 'Later'],
        ]);
        Subscriber::factory()->for($this->list, 'list')->create();
        $automation->update(['status' => 'paused']);
        Subscriber::factory()->for($this->list, 'list')->create();

        $this->travel(2)->hours();
        app(Engine::class)->tick();

        $this->assertSame(1, $automation->runs()->count());
        $this->assertSame([], $this->transport->sent);

        $automation->update(['status' => 'active']);
        app(Engine::class)->tick();
        $this->assertSame(['Later'], $this->subjects());
    }

    public function test_flow_insert_and_remove_keep_the_graph_connected(): void
    {
        $flow = new Flow(Flow::empty());
        $a = $flow->insert(null, 'next', ['type' => 'wait']);
        $c = $flow->insert($a, 'next', ['type' => 'condition']);
        $yes = $flow->insert($c, 'yes', ['type' => 'action']);
        $no = $flow->insert($c, 'no', ['type' => 'wait']);
        $b = $flow->insert($a, 'next', ['type' => 'action']); // inserted between a and the condition

        $this->assertSame($b, $flow->get($a)['next']);
        $this->assertSame($c, $flow->get($b)['next']);

        $result = $flow->remove($c);
        $this->assertSame($yes, $flow->get($b)['next'], 'yes path is kept');
        $this->assertEqualsCanonicalizing([$c, $no], $result['removed'], 'no path is deleted');

        $flow->remove($a);
        $this->assertSame($b, $flow->start());
    }

    public function test_builder_endpoints_create_edit_and_remove_steps(): void
    {
        $this->signInTo($this->workspace);
        $this->post(route('automations.store'), ['name' => 'Welcome', 'list' => $this->list->uid, 'trigger_type' => 'subscribed'])->assertRedirect();
        $automation = Automation::first();

        $this->post(route('automations.activate', $automation))->assertSessionHas('error');

        $this->post(route('automations.steps.store', $automation), ['type' => 'send', 'parent' => null, 'branch' => 'next'])->assertRedirect();
        $sendId = $automation->fresh()->flow()->start();
        $this->put(route('automations.email.update', [$automation, $sendId]), [
            'name' => 'Welcome 1', 'subject' => 'Hi {{ SUBSCRIBER_FIRST_NAME }}', 'from_name' => 'Us', 'from_email' => 'us@example.com', 'html' => '<p>Hello</p>',
        ])->assertRedirect(route('automations.show', $automation));

        $this->post(route('automations.steps.store', $automation), ['type' => 'wait', 'parent' => $sendId, 'branch' => 'next']);
        $waitId = $automation->fresh()->flow()->get($sendId)['next'];
        $this->put(route('automations.step.update', [$automation, $waitId]), ['amount' => 3, 'unit' => 'hours'])->assertRedirect();
        $this->assertSame(3, $automation->fresh()->flow()->get($waitId)['amount']);

        $this->post(route('automations.activate', $automation))->assertSessionHas('success');
        $this->get(route('automations.show', $automation))->assertOk()->assertSee('Hi');

        $this->delete(route('automations.step.destroy', [$automation, $sendId]))->assertRedirect();
        $this->assertSame($waitId, $automation->fresh()->flow()->start());
        $this->assertSame(0, $automation->emails()->count(), 'the step\'s email is removed with it');
    }

    public function test_removing_a_step_moves_waiting_contacts_forward(): void
    {
        [$automation, $ids] = $this->automation('subscribed', [
            ['type' => 'wait', 'amount' => 5, 'unit' => 'days'],
            ['type' => 'send', 'subject' => 'Next'],
        ]);
        Subscriber::factory()->for($this->list, 'list')->create();
        $this->signInTo($this->workspace);

        $this->delete(route('automations.step.destroy', [$automation, $ids[0]]));
        app(Engine::class)->tick();
        $this->travel(6)->minutes();
        app(Engine::class)->tick();

        $this->assertSame(['Next'], $this->subjects());
    }

    public function test_automation_emails_stay_out_of_the_campaign_list(): void
    {
        [$automation] = $this->automation('subscribed', [['type' => 'send', 'subject' => 'Hidden email']]);
        $this->signInTo($this->workspace);

        $this->get(route('campaigns.index'))->assertOk()->assertDontSee('Hidden email');
        $this->get(route('automations.index'))->assertOk()->assertSee($automation->name);
    }

    public function test_automation_of_another_workspace_is_not_reachable(): void
    {
        $other = Automation::create([
            'workspace_id' => Workspace::factory()->create()->id, 'mail_list_id' => MailList::factory()->create()->id,
            'name' => 'Theirs', 'trigger_type' => 'subscribed',
        ]);
        $this->signInTo($this->workspace);

        $this->get(route('automations.show', $other))->assertNotFound();
        $this->post(route('automations.steps.store', $other), ['type' => 'wait', 'branch' => 'next'])->assertNotFound();
    }
}

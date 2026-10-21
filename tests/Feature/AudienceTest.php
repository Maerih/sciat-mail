<?php

namespace Tests\Feature;

use App\Jobs\SendListEmail;
use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Segment;
use App\Models\SendingServer;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Sending\TrackingUrls;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AudienceTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected MailList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $this->list = MailList::factory()->for($this->workspace)->create();
        SendingServer::factory()->for($this->workspace)->create();
    }

    /** @return string[] */
    protected function segmentEmails(array $conditions, string $matching = 'all'): array
    {
        return Segment::applyConditions($this->list->subscribers()->getQuery(), $conditions, $matching)
            ->orderBy('email')->pluck('email')->all();
    }

    public function test_segment_conditions_on_fields_tags_status_and_engagement(): void
    {
        $make = fn ($email, $attributes = []) => Subscriber::factory()->for($this->list, 'list')->create(['email' => $email] + $attributes);
        $make('ann@a.com', ['data' => ['FIRST_NAME' => 'Ann', 'CITY' => 'Nairobi'], 'tags' => ['vip'], 'last_open_at' => now()->subDays(3)]);
        $make('bob@b.com', ['data' => ['FIRST_NAME' => 'Bob', 'CITY' => 'Mombasa'], 'tags' => ['trial'], 'last_open_at' => now()->subDays(40)]);
        $make('cat@a.com', ['data' => ['FIRST_NAME' => ''], 'status' => 'unsubscribed']);

        $this->assertSame(['ann@a.com', 'cat@a.com'], $this->segmentEmails([['field' => 'EMAIL', 'operator' => 'ends', 'value' => '@a.com']]));
        $this->assertSame(['ann@a.com'], $this->segmentEmails([['field' => 'CITY', 'operator' => 'equal', 'value' => 'Nairobi']]));
        $this->assertSame(['bob@b.com', 'cat@a.com'], $this->segmentEmails([['field' => 'CITY', 'operator' => 'not_equal', 'value' => 'Nairobi']]));
        $this->assertSame(['cat@a.com'], $this->segmentEmails([['field' => 'FIRST_NAME', 'operator' => 'blank', 'value' => '']]));
        $this->assertSame(['ann@a.com'], $this->segmentEmails([['field' => 'tags', 'operator' => 'has', 'value' => 'vip']]));
        $this->assertSame(['bob@b.com', 'cat@a.com'], $this->segmentEmails([['field' => 'tags', 'operator' => 'not_has', 'value' => 'vip']]));
        $this->assertSame(['ann@a.com'], $this->segmentEmails([['field' => 'last_open_at', 'operator' => 'within', 'value' => '7']]));
        $this->assertSame(['cat@a.com'], $this->segmentEmails([['field' => 'never_opened', 'operator' => 'is', 'value' => '']]));
        $this->assertSame(['ann@a.com', 'bob@b.com'], $this->segmentEmails([
            ['field' => 'tags', 'operator' => 'has', 'value' => 'vip'],
            ['field' => 'CITY', 'operator' => 'equal', 'value' => 'Mombasa'],
        ], 'any'));
        $this->assertSame([], $this->segmentEmails([
            ['field' => 'tags', 'operator' => 'has', 'value' => 'vip'],
            ['field' => 'CITY', 'operator' => 'equal', 'value' => 'Mombasa'],
        ], 'all'));
    }

    public function test_like_wildcards_in_segment_values_are_matched_literally(): void
    {
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'x100%@a.com']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'x1000@a.com']);

        $this->assertSame(['x100%@a.com'], $this->segmentEmails([['field' => 'EMAIL', 'operator' => 'contains', 'value' => '100%']]));
    }

    public function test_csv_import_creates_updates_and_reports_invalid_rows(): void
    {
        $this->signInTo($this->workspace);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'old@example.com', 'data' => ['FIRST_NAME' => 'Old']]);
        Blacklist::create(['workspace_id' => $this->workspace->id, 'email' => 'blocked@example.com', 'reason' => 'manual']);
        $csv = "Email;First name;Tags\nold@example.com;Renamed;vip\nNEW@example.com;New;a, b\nblocked@example.com;Blocked;\nnot-an-email;Bad;\n";

        $this->post(route('lists.import.upload', $this->list), ['file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])
            ->assertRedirect();
        $import = $this->list->imports()->first();
        $this->assertSame(4, $import->total);

        $this->get(route('lists.import.map', [$this->list, $import]))->assertOk()->assertSee('First name');
        $this->post(route('lists.import.start', [$this->list, $import]), ['mapping' => ['EMAIL', 'FIRST_NAME', 'TAGS']])->assertRedirect();

        $import->refresh();
        $this->assertSame('done', $import->status);
        $this->assertSame([2, 1, 1], [$import->created_count, $import->updated_count, $import->failed_count]);
        $this->assertSame('Renamed', $this->list->subscribers()->where('email', 'old@example.com')->first()->field('FIRST_NAME'));
        $this->assertSame(['a', 'b'], $this->list->subscribers()->where('email', 'new@example.com')->first()->tags);
        $this->assertSame('blacklisted', $this->list->subscribers()->where('email', 'blocked@example.com')->value('status'));
    }

    public function test_import_requires_exactly_one_email_column(): void
    {
        $this->signInTo($this->workspace);
        $this->post(route('lists.import.upload', $this->list), ['file' => UploadedFile::fake()->createWithContent('c.csv', "a,b\nx,y\n")]);
        $import = $this->list->imports()->first();

        $this->post(route('lists.import.start', [$this->list, $import]), ['mapping' => ['FIRST_NAME', '']])
            ->assertSessionHasErrors('mapping');
        $this->assertSame('pending', $import->fresh()->status);
    }

    public function test_double_opt_in_form_signup_sends_confirmation_and_confirm_link_subscribes(): void
    {
        $this->list->update(['double_optin' => true, 'send_welcome_email' => true]);
        $form = $this->list->forms()->create(['name' => 'Footer', 'button_text' => 'Join']);

        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'New@Example.com', 'FIRST_NAME' => 'Nia'])
            ->assertOk()->assertJson(['ok' => true]);

        $subscriber = $this->list->subscribers()->where('email', 'new@example.com')->first();
        $this->assertSame('unconfirmed', $subscriber->status);
        $this->assertSame(['new@example.com'], $this->transport->recipients());
        $this->assertStringContainsString('confirm', strtolower($this->transport->sent[0]->getOriginalMessage()->getSubject()));

        $this->get(app(TrackingUrls::class)->confirm($subscriber))->assertOk()->assertSee('Subscription confirmed');
        $this->assertSame('subscribed', $subscriber->fresh()->status);
        $this->assertCount(2, $this->transport->sent, 'welcome email follows confirmation');
        $this->assertSame(1, $form->fresh()->submissions);
    }

    public function test_confirm_link_with_bad_signature_is_rejected(): void
    {
        $subscriber = Subscriber::factory()->for($this->list, 'list')->status('unconfirmed')->create();

        $this->get('/subscribe/confirm/'.$subscriber->uid.'/badsignature')->assertNotFound();
        $this->assertSame('unconfirmed', $subscriber->fresh()->status);
    }

    public function test_form_enforces_consent_and_silently_drops_honeypot_submissions(): void
    {
        $form = $this->list->forms()->create(['name' => 'Footer', 'consent_enabled' => true, 'consent_text' => 'I agree']);

        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'a@example.com'])->assertStatus(422)->assertJsonPath('errors.consent.0', 'Please tick the consent box to subscribe.');
        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'bot@example.com', 'consent' => '1', 'website_url' => 'http://spam.example'])->assertOk();

        $this->assertSame(0, $this->list->subscribers()->count());
    }

    public function test_draft_forms_do_not_accept_submissions(): void
    {
        $form = $this->list->forms()->create(['name' => 'Footer', 'status' => 'draft']);

        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'a@example.com'])->assertNotFound();
    }

    public function test_blacklisted_address_cannot_resubscribe_through_a_form(): void
    {
        Blacklist::create(['workspace_id' => null, 'email' => 'spam@example.com', 'reason' => 'complaint']);
        $form = $this->list->forms()->create(['name' => 'Footer']);

        $this->postJson(route('subscribe.store', $form->uid), ['EMAIL' => 'spam@example.com'])->assertOk();

        $this->assertSame('blacklisted', $this->list->subscribers()->where('email', 'spam@example.com')->value('status'));
        $this->assertSame([], $this->transport->sent);
    }

    public function test_unsubscribe_notification_is_queued_when_enabled(): void
    {
        Queue::fake([SendListEmail::class]);
        $this->list->update(['unsubscribe_notification' => true]);
        $subscriber = Subscriber::factory()->for($this->list, 'list')->create();

        app(SubscriptionService::class)->unsubscribe($subscriber);

        Queue::assertPushed(SendListEmail::class, fn ($job) => $job->type === 'unsubscribe_notice' && $job->subscriberId === $subscriber->id);
    }

    public function test_bulk_actions_apply_to_selected_or_all_filtered_subscribers(): void
    {
        $this->signInTo($this->workspace);
        $a = Subscriber::factory()->for($this->list, 'list')->create(['email' => 'a@example.com']);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'b@example.com']);
        Subscriber::factory()->for($this->list, 'list')->status('unsubscribed')->create(['email' => 'c@example.com']);

        $this->post(route('lists.subscribers.bulk', $this->list), ['action' => 'tag', 'scope' => 'selected', 'ids' => [$a->uid], 'tags' => 'vip, 2026'])
            ->assertRedirect();
        $this->assertSame(['vip', '2026'], $a->fresh()->tags);

        $this->post(route('lists.subscribers.bulk', ['list' => $this->list, 'status' => 'subscribed']), ['action' => 'unsubscribe', 'scope' => 'all']);
        $this->assertSame(3, $this->list->subscribers()->where('status', 'unsubscribed')->count());
    }

    public function test_manual_add_validates_required_custom_fields_and_duplicate_email(): void
    {
        $this->signInTo($this->workspace);
        $this->list->fields()->create(['label' => 'Company', 'tag' => 'COMPANY', 'type' => 'text', 'required' => true, 'sort' => 9]);
        Subscriber::factory()->for($this->list, 'list')->create(['email' => 'taken@example.com']);

        $this->post(route('lists.subscribers.store', $this->list), ['EMAIL' => 'taken@example.com'])
            ->assertSessionHasErrors(['EMAIL', 'COMPANY']);

        $this->post(route('lists.subscribers.store', $this->list), ['EMAIL' => 'ok@example.com', 'COMPANY' => 'Acme'])
            ->assertRedirect(route('lists.subscribers.index', $this->list));
        $this->assertSame('Acme', $this->list->subscribers()->where('email', 'ok@example.com')->first()->field('COMPANY'));
    }

    public function test_segment_builder_ignores_unknown_fields_and_operators(): void
    {
        $this->signInTo($this->workspace);

        $this->post(route('lists.segments.store', $this->list), [
            'name' => 'Mixed',
            'matching' => 'all',
            'conditions' => [
                ['field' => 'EMAIL', 'operator' => 'contains', 'value' => '@'],
                ['field' => 'password', 'operator' => 'equal', 'value' => 'x'],
                ['field' => 'EMAIL', 'operator' => 'drop table', 'value' => 'x'],
            ],
        ])->assertRedirect();

        $this->assertSame([['field' => 'EMAIL', 'operator' => 'contains', 'value' => '@']], Segment::first()->conditions);
    }

    public function test_form_embed_code_lists_visible_fields_and_posts_to_public_endpoint(): void
    {
        $this->signInTo($this->workspace);
        $this->list->fields()->create(['label' => 'Hidden note', 'tag' => 'NOTE', 'type' => 'text', 'visible' => false, 'sort' => 9]);
        $form = $this->list->forms()->create(['name' => 'Footer']);

        $this->get(route('forms.embed', $form))
            ->assertOk()
            ->assertSee(route('subscribe.store', $form->uid), false)
            ->assertSee('name="FIRST_NAME"', false)
            ->assertDontSee('name="NOTE"', false);
    }

    public function test_form_of_another_workspace_cannot_be_edited(): void
    {
        $foreignForm = MailList::factory()->create()->forms()->create(['name' => 'Theirs']);
        $this->signInTo($this->workspace);

        $this->get(route('forms.edit', $foreignForm->uid))->assertNotFound();
    }
}

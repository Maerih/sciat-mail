<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Signature;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Sending\MergeTags;
use App\Sending\MessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_manages_signatures_and_one_is_default(): void
    {
        $workspace = Workspace::factory()->create();
        $this->signInTo($workspace);

        $this->get(route('sending.signatures.index'))->assertOk()->assertSee('Email signatures');
        $this->get(route('sending.signatures.create'))->assertOk();
        $this->post(route('sending.signatures.store'), ['name' => 'Ann', 'html' => '<b>Ann</b>'])->assertRedirect();
        $this->post(route('sending.signatures.store'), ['name' => 'Bob', 'html' => '<b>Bob</b>'])->assertRedirect();

        [$ann, $bob] = Signature::orderBy('id')->get();
        $this->assertTrue($ann->is_default, 'the first signature becomes the default');
        $this->assertFalse($bob->is_default);

        $this->post(route('sending.signatures.default', $bob))->assertRedirect();
        $this->assertFalse($ann->fresh()->is_default);
        $this->assertTrue($bob->fresh()->is_default);
        $this->get(route('sending.signatures.edit', $bob))->assertOk();

        $this->delete(route('sending.signatures.destroy', $bob))->assertRedirect();
        $this->assertTrue($ann->fresh()->is_default, 'deleting the default promotes another');
    }

    public function test_signatures_of_other_clients_are_not_reachable(): void
    {
        $signature = new Signature(['name' => 'Theirs', 'html' => 'x']);
        $signature->workspace_id = Workspace::factory()->create()->id;
        $signature->save();
        $this->signInTo(Workspace::factory()->create());

        $this->get(route('sending.signatures.edit', $signature))->assertNotFound();
    }

    public function test_signature_merge_tag_renders_html_in_emails_and_text_in_plain_bodies(): void
    {
        $workspace = Workspace::factory()->create();
        $signature = new Signature(['name' => 'Ann', 'html' => '<p><strong>Ann Lee</strong><br>Acme</p>']);
        $signature->workspace_id = $workspace->id;
        $signature->save();
        $list = MailList::factory()->for($workspace)->create();
        $subscriber = Subscriber::factory()->for($list, 'list')->create();
        $campaign = Campaign::factory()->for($workspace)->create(['html' => '<p>Hello</p>{{ SIGNATURE }}']);
        $delivery = Delivery::create(['campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id, 'workspace_id' => $workspace->id, 'email' => $subscriber->email]);

        $builder = app(MessageBuilder::class);
        $values = $builder->values($campaign, $delivery, $subscriber);

        $this->assertStringContainsString('<strong>Ann Lee</strong>', $builder->renderHtml($campaign, $delivery, $values, track: false));
        $this->assertSame("Thanks\nAnn Lee\nAcme", MergeTags::render("Thanks\n{{ SIGNATURE }}", $values, false));
    }
}

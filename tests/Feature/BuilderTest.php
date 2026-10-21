<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create();
        $this->signInTo($this->workspace);
    }

    protected function payload(string $html = '<html><body><p>Built</p></body></html>'): array
    {
        return ['html' => $html, 'project' => json_encode(['pages' => [['component' => 'x']]])];
    }

    public function test_builder_saves_html_and_project_for_templates(): void
    {
        $template = Template::create(['workspace_id' => $this->workspace->id, 'name' => 'Mine', 'html' => '<p>old</p>']);

        $this->get(route('templates.builder', $template))->assertOk()->assertSee('builderConfig', false);
        $this->putJson(route('templates.builder.save', $template), $this->payload())->assertOk()->assertJson(['ok' => true]);

        $template->refresh();
        $this->assertSame('<html><body><p>Built</p></body></html>', $template->html);
        $this->assertJson($template->builder_json);
    }

    public function test_system_templates_open_as_a_copy_and_cannot_be_overwritten(): void
    {
        $system = Template::create(['workspace_id' => null, 'name' => 'Starter', 'html' => '<p>s</p>']);

        $this->get(route('templates.builder', $system))->assertRedirect(route('templates.create', ['from' => $system->uid]));
        $this->putJson(route('templates.builder.save', $system), $this->payload())->assertForbidden();
    }

    public function test_campaign_content_can_be_built_until_it_is_launched(): void
    {
        $campaign = Campaign::factory()->for($this->workspace)->create();

        $this->get(route('campaigns.builder', $campaign))->assertOk();
        $this->putJson(route('campaigns.builder.save', $campaign), $this->payload())->assertOk();
        $this->assertNotNull($campaign->fresh()->builder_json);

        $campaign->forceFill(['status' => 'done'])->save();
        $this->putJson(route('campaigns.builder.save', $campaign), $this->payload())->assertStatus(409);
    }

    public function test_hand_editing_html_discards_the_stale_design(): void
    {
        $template = Template::create(['workspace_id' => $this->workspace->id, 'name' => 'Mine', 'html' => '<p>a</p>', 'builder_json' => '{"pages":[]}']);

        $this->put(route('templates.update', $template), ['name' => 'Mine', 'html' => '<p>a</p>']);
        $this->assertNotNull($template->fresh()->builder_json, 'unchanged HTML keeps the design');

        $this->put(route('templates.update', $template), ['name' => 'Mine', 'html' => '<p>edited by hand</p>']);
        $this->assertNull($template->fresh()->builder_json);
    }

    public function test_applying_a_template_to_a_campaign_carries_its_design(): void
    {
        $template = Template::create(['workspace_id' => $this->workspace->id, 'name' => 'Designed', 'html' => '<p>t</p>', 'builder_json' => '{"pages":[1]}']);
        $campaign = Campaign::factory()->for($this->workspace)->create();

        $this->post(route('campaigns.content.template', $campaign), ['template' => $template->uid]);

        $this->assertSame('{"pages":[1]}', $campaign->fresh()->builder_json);
    }

    public function test_image_upload_returns_absolute_urls_in_the_workspace_folder(): void
    {
        Storage::fake('public');

        $response = $this->postJson(route('builder.upload'), ['files' => [UploadedFile::fake()->image('hero.png', 600, 300)]])->assertOk();

        $src = $response->json('data.0.src');
        $this->assertStringStartsWith('http', $src);
        $this->assertStringContainsString('uploads/'.$this->workspace->uid.'/', $src);
        $this->assertCount(1, Storage::disk('public')->files('uploads/'.$this->workspace->uid));
        $this->postJson(route('builder.upload'), ['files' => [UploadedFile::fake()->create('evil.php', 1, 'application/x-php')]])->assertStatus(422);
    }

    public function test_builder_of_another_workspace_is_not_reachable(): void
    {
        $foreign = Campaign::factory()->create();

        $this->get(route('campaigns.builder', $foreign))->assertNotFound();
        $this->putJson(route('campaigns.builder.save', $foreign), $this->payload())->assertNotFound();
    }
}

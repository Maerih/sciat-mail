<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\Workspace;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CybersecurityTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sciat_templates_are_seeded_with_required_merge_tags(): void
    {
        (new TemplateSeeder)->run();
        $templates = Template::where('category', 'cybersecurity')->get();

        $this->assertCount(count(TemplateSeeder::CYBERSECURITY), $templates);
        foreach ($templates as $template) {
            $this->assertStringContainsString('{{ UNSUBSCRIBE_URL }}', $template->html, $template->name);
            $this->assertStringContainsString('{{ WEB_VIEW_URL }}', $template->html, $template->name);
            $this->assertStringNotContainsString('{{{{', $template->html, $template->name);
        }

        (new TemplateSeeder)->run();
        $this->assertCount(count(TemplateSeeder::CYBERSECURITY), Template::where('category', 'cybersecurity')->get(), 're-running does not duplicate');

        $this->signInTo(Workspace::factory()->create());
        $this->get(route('templates.index'))->assertOk()->assertSee('SCIAT Cyber News — monthly newsletter');
    }
}

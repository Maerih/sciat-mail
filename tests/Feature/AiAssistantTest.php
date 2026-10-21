<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Setting;
use App\Models\Workspace;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiEngine;
use App\Services\Ai\AiException;
use App\Services\Ai\OllamaEngine;
use App\Services\Ai\OpenAiEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    /** @var array<int, array{system: string, prompt: string, schema: ?array}> */
    protected array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::factory()->create(['company' => 'Bloom Bakery']);
    }

    /** Binds the assistant to a scripted engine returning $responses in order (or throwing them). */
    protected function fakeEngine(array $responses): void
    {
        Setting::put(['ai_provider' => 'anthropic', 'ai_api_key' => Crypt::encryptString('test-key')]);
        $calls = &$this->calls;
        $engine = new class($responses, $calls) implements AiEngine
        {
            public function __construct(private array $responses, private array &$calls) {}

            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                $this->calls[] = compact('system', 'prompt', 'schema');
                $next = array_shift($this->responses);
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            }
        };
        $this->app->bind(AiAssistant::class, fn () => new AiAssistant($engine));
    }

    public function test_subject_suggestions_use_the_email_content_and_a_json_schema(): void
    {
        $this->fakeEngine([json_encode(['suggestions' => [
            ['angle' => 'curiosity', 'subject' => 'The secret behind our sourdough', 'preheader' => 'It takes 48 hours'],
            ['angle' => 'urgency', 'subject' => 'Last day for fresh croissants', 'preheader' => 'Order by noon'],
        ]])]);
        $this->signInTo($this->workspace);

        $this->postJson(route('ai.subjects'), ['subject' => 'Weekly news', 'content' => '<p>Our new <b>sourdough</b> is here</p>'])
            ->assertOk()
            ->assertJsonPath('suggestions.0.subject', 'The secret behind our sourdough')
            ->assertJsonPath('suggestions.1.angle', 'urgency');

        $this->assertStringContainsString('Our new sourdough is here', $this->calls[0]['prompt']);
        $this->assertStringContainsString('Bloom Bakery', $this->calls[0]['prompt']);
        $this->assertSame(['suggestions'], $this->calls[0]['schema']['required']);
        $this->assertDatabaseHas('ai_logs', ['workspace_id' => $this->workspace->id, 'action' => 'subjects', 'success' => true]);
    }

    public function test_rewrite_returns_the_new_text(): void
    {
        $this->fakeEngine(["Hi {{ SUBSCRIBER_FIRST_NAME }}, fresh bread is waiting!\n"]);
        $this->signInTo($this->workspace);

        $this->postJson(route('ai.rewrite'), ['text' => 'Hello {{ SUBSCRIBER_FIRST_NAME }}, we have bread.', 'instruction' => 'friendlier'])
            ->assertOk()->assertJson(['text' => 'Hi {{ SUBSCRIBER_FIRST_NAME }}, fresh bread is waiting!']);
        $this->assertStringContainsString('warmer and more conversational', $this->calls[0]['prompt']);
    }

    public function test_draft_is_sanitised_and_wrapped_in_an_email_template(): void
    {
        $this->fakeEngine([json_encode([
            'subject' => 'Autumn sale: 20% off',
            'preheader' => 'Until Sunday',
            'body_html' => '<h1 style="color:red">Sale!</h1><p onclick="steal()">Hi {{ SUBSCRIBER_FIRST_NAME | there }}</p><script>alert(1)</script>'
                .'<p><a href="javascript:alert(1)">bad</a> <a href="https://shop.test/sale" target="_blank">Shop</a></p><img src="x">',
        ])]);
        $this->signInTo($this->workspace);

        $response = $this->postJson(route('ai.draft'), ['brief' => 'Announce the autumn sale with 20% off', 'cta_text' => 'Shop'])->assertOk();

        $html = $response->json('html');
        $this->assertSame('Autumn sale: 20% off', $response->json('subject'));
        $this->assertStringContainsString('<h1>Sale!</h1>', $html);
        $this->assertStringContainsString('<a href="https://shop.test/sale">Shop</a>', $html);
        $this->assertStringContainsString('{{ UNSUBSCRIBE_URL }}', $html);
        foreach (['onclick', '<script', 'javascript:', '<img', 'style="color:red"'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }

    public function test_engine_errors_and_unreadable_output_return_a_clear_message(): void
    {
        $this->fakeEngine([new AiException('Claude declined this request. Try rephrasing it.'), 'not json at all']);
        $this->signInTo($this->workspace);

        $this->postJson(route('ai.subjects'), [])->assertStatus(422)->assertJson(['message' => 'Claude declined this request. Try rephrasing it.']);
        $this->postJson(route('ai.subjects'), [])->assertStatus(422)->assertJson(['message' => 'The AI response could not be read. Please try again.']);
        $this->assertDatabaseHas('ai_logs', ['action' => 'subjects', 'success' => false]);
    }

    public function test_unconfigured_assistant_explains_how_to_set_it_up_and_hides_buttons(): void
    {
        $this->signInTo($this->workspace);
        $campaign = Campaign::factory()->for($this->workspace)->create();

        $this->assertFalse(AiAssistant::isConfigured());
        $this->postJson(route('ai.rewrite'), ['text' => 'x', 'instruction' => 'shorter'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not set up'));
        $this->get(route('campaigns.setup', $campaign))->assertOk()->assertDontSee('Suggest with AI');
    }

    public function test_admin_configures_the_engine_with_an_encrypted_key(): void
    {
        $this->signInTo($this->workspace, 'admin');

        $this->put(route('platform.ai.update'), ['ai_provider' => 'ollama'])->assertSessionHasErrors(['ai_model', 'ai_base_url']);
        $this->put(route('platform.ai.update'), ['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-ant-secret'])->assertRedirect();

        $this->assertTrue(AiAssistant::isConfigured());
        $this->assertStringNotContainsString('sk-ant-secret', Setting::get('ai_api_key'));
        $this->put(route('platform.ai.update'), ['ai_provider' => 'anthropic', 'ai_api_key' => ''])->assertRedirect();
        $this->assertSame('sk-ant-secret', Crypt::decryptString(Setting::get('ai_api_key')), 'blank key keeps the saved one');
        $this->get(route('platform.ai'))->assertOk()->assertDontSee('sk-ant-secret');

        $campaign = Campaign::factory()->for($this->workspace)->create();
        $this->get(route('campaigns.setup', $campaign))->assertSee('Suggest with AI');
    }

    public function test_members_cannot_change_ai_settings(): void
    {
        $this->signInTo($this->workspace);

        $this->get(route('platform.ai'))->assertForbidden();
        $this->put(route('platform.ai.update'), ['ai_provider' => 'openai', 'ai_api_key' => 'x', 'ai_model' => 'm'])->assertForbidden();
    }

    public function test_openai_and_ollama_engines_send_schema_constrained_requests(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]]]),
            'ollama.local:11434/*' => Http::response(['message' => ['content' => '{"ok":true}']]),
        ]);
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];

        $this->assertSame('{"ok":true}', (new OpenAiEngine('key', 'my-model'))->complete('sys', 'hi', $schema));
        $this->assertSame('{"ok":true}', (new OllamaEngine('http://ollama.local:11434', 'llama3.1'))->complete('sys', 'hi', $schema));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com/v1/chat/completions') && $r['response_format']['json_schema']['schema'] === $schema
            && $r->header('Authorization')[0] === 'Bearer key');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/chat') && $r['format'] === $schema && $r['stream'] === false);
    }

    public function test_provider_failures_become_ai_exceptions(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid key']], 401)]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('Invalid key');
        (new OpenAiEngine('bad', 'm'))->complete('s', 'p');
    }
}

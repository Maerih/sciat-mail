<?php

namespace App\Services\Ai;

use App\Models\Setting;
use App\Models\Workspace;
use App\Sending\HtmlToText;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Copywriting helpers for campaigns and automations: subject line ideas, rewrites and
 * full email drafts. The engine (Claude, OpenAI or Ollama) is chosen in platform settings.
 */
class AiAssistant
{
    public const PROVIDERS = [
        'anthropic' => 'Anthropic Claude',
        'openai' => 'OpenAI',
        'ollama' => 'Ollama (self-hosted)',
    ];

    /** Creative angles used for subject line ideas and A/B variants. */
    public const ANGLES = ['curiosity', 'benefit', 'urgency', 'question', 'number', 'personalization'];

    public const REWRITES = [
        'shorter' => 'Make it shorter and tighter',
        'friendlier' => 'Make it warmer and more conversational',
        'persuasive' => 'Make it more persuasive, with a clear call to action',
        'professional' => 'Make it more formal and professional',
        'grammar' => 'Fix spelling and grammar only',
    ];

    public function __construct(protected ?AiEngine $engine = null) {}

    public static function isConfigured(): bool
    {
        $provider = Setting::get('ai_provider');

        return $provider === 'ollama' ? filled(Setting::get('ai_base_url')) && filled(Setting::get('ai_model'))
            : in_array($provider, ['anthropic', 'openai'], true) && filled(Setting::get('ai_api_key'));
    }

    public static function engineFromSettings(): AiEngine
    {
        $key = Setting::get('ai_api_key') ? Crypt::decryptString(Setting::get('ai_api_key')) : '';
        $model = Setting::get('ai_model');

        return match (Setting::get('ai_provider')) {
            'anthropic' => new AnthropicEngine($key, $model ?: AnthropicEngine::DEFAULT_MODEL),
            'openai' => new OpenAiEngine($key, (string) $model, Setting::get('ai_base_url') ?: 'https://api.openai.com/v1'),
            'ollama' => new OllamaEngine((string) Setting::get('ai_base_url'), (string) $model),
            default => throw new AiException('The AI assistant is not set up yet. An administrator can configure it under Platform settings → AI assistant.'),
        };
    }

    /**
     * @param  array{subject?: ?string, content?: ?string, audience?: ?string, brand?: ?string}  $context
     * @return array<int, array{angle: string, subject: string, preheader: string}>
     */
    public function subjectLines(Workspace $workspace, array $context, int $count = 6): array
    {
        $prompt = "Write {$count} email subject lines for the email described below, one per angle, cycling through these angles: "
            .implode(', ', self::ANGLES).".\n"
            .'Each subject should be under 60 characters, specific to the content, and free of spam-trigger styling (no ALL CAPS words, no more than one emoji, no misleading claims). '
            .'Give each one a matching preheader (under 90 characters) that complements rather than repeats it. '
            ."Personalization merge tags such as {{ SUBSCRIBER_FIRST_NAME | there }} may be used sparingly.\n\n"
            .$this->contextBlock($context);

        $data = $this->json($workspace, 'subjects', $prompt, [
            'type' => 'object',
            'properties' => ['suggestions' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['angle' => ['type' => 'string'], 'subject' => ['type' => 'string'], 'preheader' => ['type' => 'string']],
                'required' => ['angle', 'subject', 'preheader'],
                'additionalProperties' => false,
            ]]],
            'required' => ['suggestions'],
            'additionalProperties' => false,
        ]);

        return collect($data['suggestions'] ?? [])
            ->filter(fn ($s) => filled($s['subject'] ?? null))
            ->map(fn ($s) => ['angle' => (string) $s['angle'], 'subject' => mb_substr(trim($s['subject']), 0, 150), 'preheader' => mb_substr(trim((string) ($s['preheader'] ?? '')), 0, 200)])
            ->take($count)->values()->all();
    }

    public function rewrite(Workspace $workspace, string $text, string $instruction): string
    {
        $how = self::REWRITES[$instruction] ?? $instruction;
        $prompt = "Rewrite the email text below. Instruction: {$how}.\n"
            ."Keep the original meaning, facts, links and any {{ MERGE_TAGS }} exactly as they are. Return only the rewritten text, with no preamble or commentary.\n\n"
            ."<text>\n{$text}\n</text>";

        return trim($this->run($workspace, 'rewrite', $prompt, null, 4000));
    }

    /**
     * @param  array{brief: string, tone?: ?string, length?: ?string, cta_text?: ?string, cta_url?: ?string}  $input
     * @return array{subject: string, preheader: string, html: string}
     */
    public function draftEmail(Workspace $workspace, array $input): array
    {
        $prompt = 'Write a marketing email for the brief below. Tone: '.($input['tone'] ?? 'friendly').'. Length: '.($input['length'] ?? 'medium').".\n"
            .'Return the body as simple, email-safe HTML: <h1>/<h2> headings, <p> paragraphs, <ul> lists and <a> links only — no <html>, <head>, <style>, scripts, images or inline CSS. '
            ."Open with a greeting that uses {{ SUBSCRIBER_FIRST_NAME | there }}. Do not add an unsubscribe link or footer; the platform adds those.\n"
            .(! empty($input['cta_text']) ? 'End with one call-to-action link with the text "'.$input['cta_text'].'"'.(! empty($input['cta_url']) ? ' pointing to '.$input['cta_url'] : '').".\n" : '')
            ."\nCompany: ".($workspace->company ?: $workspace->name)."\n<brief>\n{$input['brief']}\n</brief>";

        $data = $this->json($workspace, 'draft', $prompt, [
            'type' => 'object',
            'properties' => ['subject' => ['type' => 'string'], 'preheader' => ['type' => 'string'], 'body_html' => ['type' => 'string']],
            'required' => ['subject', 'preheader', 'body_html'],
            'additionalProperties' => false,
        ], 8000);

        return [
            'subject' => trim((string) ($data['subject'] ?? '')),
            'preheader' => trim((string) ($data['preheader'] ?? '')),
            'html' => view('emails.ai-draft', ['body' => self::sanitize((string) ($data['body_html'] ?? '')), 'workspace' => $workspace])->render(),
        ];
    }

    /** Keeps only the simple tags drafts are allowed to use; strips attributes except safe hrefs. */
    public static function sanitize(string $html): string
    {
        $html = strip_tags($html, '<h1><h2><h3><p><ul><ol><li><a><strong><em><b><i><br>');
        $html = preg_replace_callback('/<(\w+)([^>]*)>/', function ($m) {
            $tag = strtolower($m[1]);
            if ($tag === 'a' && preg_match('/href\s*=\s*["\']((?:https?:\/\/|\{\{)[^"\']*)["\']/i', $m[2], $href)) {
                return '<a href="'.e($href[1]).'">';
            }

            return '<'.$tag.'>';
        }, $html);

        return trim($html);
    }

    /** @param array{subject?: ?string, content?: ?string, audience?: ?string, brand?: ?string} $context */
    protected function contextBlock(array $context): string
    {
        $content = isset($context['content']) ? mb_substr(HtmlToText::convert((string) $context['content']), 0, 6000) : '';

        return collect([
            'Brand' => $context['brand'] ?? null,
            'Audience' => $context['audience'] ?? null,
            'Current subject' => $context['subject'] ?? null,
            'Email content' => $content ?: null,
        ])->filter()->map(fn ($v, $k) => "{$k}:\n{$v}")->implode("\n\n") ?: 'No content written yet — suggest general subject lines for a newsletter from this brand.';
    }

    /** @return array<string, mixed> */
    protected function json(Workspace $workspace, string $action, string $prompt, array $schema, int $maxTokens = 4000): array
    {
        $raw = $this->run($workspace, $action, $prompt, $schema, $maxTokens);
        // Some self-hosted models wrap JSON in a code fence
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new AiException('The AI response could not be read. Please try again.');
        }

        return $data;
    }

    protected function run(Workspace $workspace, string $action, string $prompt, ?array $schema, int $maxTokens): string
    {
        $system = 'You are an expert email marketing copywriter working inside an email platform. '
            .'Write clear, honest, on-brand copy that respects the reader and follows anti-spam best practice.';
        $provider = (string) (Setting::get('ai_provider') ?? 'none');

        try {
            $result = ($this->engine ?? static::engineFromSettings())->complete($system, $prompt, $schema, $maxTokens);
            $this->log($workspace, $action, $provider, true);

            return $result;
        } catch (AiException $e) {
            $this->log($workspace, $action, $provider, false, $e->getMessage());
            throw $e;
        }
    }

    protected function log(Workspace $workspace, string $action, string $provider, bool $success, ?string $error = null): void
    {
        DB::table('ai_logs')->insert([
            'workspace_id' => $workspace->id, 'user_id' => auth()->id(), 'action' => $action, 'provider' => $provider,
            'success' => $success, 'error' => $error ? mb_substr($error, 0, 250) : null, 'created_at' => now(),
        ]);
    }
}

<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** OpenAI Chat Completions over HTTP. */
class OpenAiEngine implements AiEngine
{
    public function __construct(protected string $apiKey, protected string $model, protected string $baseUrl = 'https://api.openai.com/v1') {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        $body = [
            'model' => $this->model,
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
        ];
        if ($schema) {
            $body['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => 'result', 'schema' => $schema, 'strict' => true]];
        }

        try {
            $response = Http::timeout(120)->withToken($this->apiKey)->post(rtrim($this->baseUrl, '/').'/chat/completions', $body);
        } catch (ConnectionException) {
            throw new AiException('Could not reach the OpenAI API.');
        }
        if ($response->failed()) {
            throw new AiException('OpenAI returned an error: '.($response->json('error.message') ?? $response->status()));
        }

        $text = (string) $response->json('choices.0.message.content');
        if (trim($text) === '') {
            throw new AiException($response->json('choices.0.message.refusal') ? 'OpenAI declined this request.' : 'OpenAI returned an empty response.');
        }

        return $text;
    }
}

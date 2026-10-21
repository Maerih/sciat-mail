<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Self-hosted models through Ollama's /api/chat — no data leaves your servers. */
class OllamaEngine implements AiEngine
{
    public function __construct(protected string $baseUrl, protected string $model) {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        $body = [
            'model' => $this->model,
            'stream' => false,
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
            'options' => ['num_predict' => $maxTokens],
        ];
        if ($schema) {
            $body['format'] = $schema;
        }

        try {
            $response = Http::timeout(300)->post(rtrim($this->baseUrl, '/').'/api/chat', $body);
        } catch (ConnectionException) {
            throw new AiException('Could not reach the Ollama server at '.$this->baseUrl.'.');
        }
        if ($response->failed()) {
            throw new AiException('Ollama returned an error: '.($response->json('error') ?? $response->status()));
        }

        $text = (string) $response->json('message.content');
        if (trim($text) === '') {
            throw new AiException('Ollama returned an empty response.');
        }

        return $text;
    }
}

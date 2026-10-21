<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude via the official Anthropic PHP SDK. Uses Claude Opus 5.5 by default with
 * server-side refusal fallbacks ("default" routing) enabled.
 */
class AnthropicEngine implements AiEngine
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';

    public function __construct(protected string $apiKey, protected string $model = self::DEFAULT_MODEL) {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        $outputConfig = ['effort' => 'medium'];
        if ($schema) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $schema];
        }

        try {
            $message = (new Client(apiKey: $this->apiKey))->beta->messages->create(
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->model,
                fallbacks: 'default',
                outputConfig: $outputConfig,
                system: $system,
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new AiException('The Anthropic API key was rejected. Check it under Platform settings → AI assistant.');
        } catch (RateLimitException) {
            throw new AiException('Claude is rate-limiting requests right now. Try again in a minute.');
        } catch (APIStatusException $e) {
            throw new AiException('Claude returned an error: '.$e->getMessage());
        } catch (APIConnectionException) {
            throw new AiException('Could not reach the Anthropic API.');
        }

        // A refusal means no usable content, even when text blocks are present
        if ($message->stopReason === 'refusal') {
            throw new AiException('Claude declined this request. Try rephrasing it.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        if (trim($text) === '') {
            throw new AiException('Claude returned an empty response.');
        }

        return $text;
    }
}

<?php

namespace App\Services\Ai;

interface AiEngine
{
    /**
     * One-shot completion. With a JSON schema the engine must return a JSON string matching it.
     *
     * @param  array<string, mixed>|null  $schema
     *
     * @throws AiException
     */
    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string;
}

<?php

namespace App\Services\Warmup;

use App\Models\WarmupMailbox;

interface WarmupInbox
{
    /**
     * Finds warmup emails by their reference token. Ones found in spam are moved to the inbox;
     * every found email is marked read and important, which teaches the provider to trust the sender.
     *
     * @param  string[]  $tokens
     * @return array<string, string> token => 'inbox' | 'spam'
     */
    public function scan(WarmupMailbox $mailbox, array $tokens): array;
}

<?php

namespace App\Services\Bounces;

use App\Models\BounceHandler;

interface Mailbox
{
    /**
     * Unread messages, oldest first.
     *
     * @return iterable<array{id: mixed, raw: string}>
     */
    public function unread(BounceHandler $handler, int $limit): iterable;

    public function markProcessed(BounceHandler $handler, mixed $id, bool $delete): void;

    public function close(): void;
}

<?php

namespace App\Console\Commands;

use App\Services\Inbox\InboxSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inbox:sync')]
#[Description('Fetch new mail for every team-inbox mailbox')]
class InboxSyncCommand extends Command
{
    public function handle(InboxSync $sync): int
    {
        $count = $sync->syncAll();
        $count && $this->info("Stored {$count} new message(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Warmup\EmailWarmup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('warmup:send')]
#[Description('Send this hour\'s share of email-warmup messages to the seed inboxes')]
class WarmupSend extends Command
{
    public function handle(EmailWarmup $warmup): int
    {
        $sent = $warmup->sendDue();
        $sent && $this->info("Sent {$sent} warmup email(s).");

        return self::SUCCESS;
    }
}

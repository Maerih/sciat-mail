<?php

namespace App\Console\Commands;

use App\Services\Warmup\EmailWarmup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('warmup:check')]
#[Description('Check seed inboxes for warmup emails, rescue spam and record inbox placement')]
class WarmupCheck extends Command
{
    public function handle(EmailWarmup $warmup): int
    {
        $found = $warmup->check();
        $found && $this->info("Checked {$found} warmup email(s).");

        return self::SUCCESS;
    }
}

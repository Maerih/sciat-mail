<?php

namespace App\Console\Commands;

use App\Services\SendingHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('health:check')]
#[Description('Check each client\'s bounce and complaint rates and suspend sending when over the limits')]
class CheckSendingHealth extends Command
{
    public function handle(): int
    {
        $count = SendingHealth::checkAll();
        if ($count > 0) {
            $this->warn("Suspended sending for {$count} client(s).");
        }

        return self::SUCCESS;
    }
}

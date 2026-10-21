<?php

namespace App\Console\Commands;

use App\Services\Automation\Engine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:tick')]
#[Description('Fire date-based and recurring automation triggers and resume waiting runs')]
class AutomationTick extends Command
{
    public function handle(Engine $engine): int
    {
        $engine->tick();

        return self::SUCCESS;
    }
}

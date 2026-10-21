<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Services\Automation\Engine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAutomationRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(Engine $engine): void
    {
        if ($run = AutomationRun::find($this->runId)) {
            $engine->process($run);
        }
    }
}

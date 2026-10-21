<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Services\Automation\Engine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TriggerAutomations implements ShouldQueue
{
    use Queueable;

    /** @param array{tag?: string, field?: string, value?: string} $context */
    public function __construct(public int $subscriberId, public string $event, public array $context = []) {}

    public function handle(Engine $engine): void
    {
        if ($subscriber = Subscriber::find($this->subscriberId)) {
            $engine->handleEvent($subscriber, $this->event, $this->context);
        }
    }
}

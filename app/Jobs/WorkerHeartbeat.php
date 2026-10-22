<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Queued every minute; when a worker processes it, Settings → Cron & queue shows the worker as alive. */
class WorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('heartbeat:worker', now()->toIso8601String(), now()->addDay());
    }
}

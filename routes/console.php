<?php

use App\Jobs\WorkerHeartbeat;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('automations:tick')->everyMinute()->withoutOverlapping();
Schedule::command('app:backup')->dailyAt('02:30')->withoutOverlapping()
    ->when(fn () => Setting::bool('backups_enabled', true));
Schedule::command('inbox:sync')->everyMinute()->withoutOverlapping(10);
Schedule::command('warmup:send')->hourly()->withoutOverlapping();
Schedule::command('warmup:check')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('health:check')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bounces:fetch')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => Cache::put('heartbeat:scheduler', now()->toIso8601String(), now()->addDay()))->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new WorkerHeartbeat)->everyMinute();

// Shared hosting: work the queue from cron. Defined last so the other tasks run first each minute.
if (config('queue.cron_worker')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --timeout=50 --sleep=1')
        ->everyMinute()->withoutOverlapping(5)->name('cron-queue-worker');
}

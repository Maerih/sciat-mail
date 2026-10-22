<?php

use App\Jobs\WorkerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('automations:tick')->everyMinute()->withoutOverlapping();
Schedule::command('health:check')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bounces:fetch')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => Cache::put('heartbeat:scheduler', now()->toIso8601String(), now()->addDay()))->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new WorkerHeartbeat)->everyMinute();

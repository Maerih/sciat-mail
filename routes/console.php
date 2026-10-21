<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('automations:tick')->everyMinute()->withoutOverlapping();
Schedule::command('bounces:fetch')->everyFiveMinutes()->withoutOverlapping();

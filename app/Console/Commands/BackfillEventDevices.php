<?php

namespace App\Console\Commands;

use App\Models\DeliveryEvent;
use App\Services\UserAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:backfill-devices')]
#[Description('Classify device, OS and email client for opens and clicks recorded before device reports existed')]
class BackfillEventDevices extends Command
{
    public function handle(): int
    {
        $count = 0;
        DeliveryEvent::whereIn('type', ['open', 'click'])->whereNull('client')->whereNotNull('user_agent')
            ->chunkById(1000, function ($events) use (&$count) {
                foreach ($events as $event) {
                    $event->forceFill(UserAgent::parse($event->user_agent))->saveQuietly();
                    $count++;
                }
            });
        $this->info("Classified {$count} event(s).");

        return self::SUCCESS;
    }
}

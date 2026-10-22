<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use App\Sms\SmsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:dispatch-scheduled')]
#[Description('Queue scheduled campaigns whose send time has arrived')]
class DispatchScheduledCampaigns extends Command
{
    public function handle(CampaignService $campaigns): int
    {
        $count = $campaigns->dispatchDue() + app(SmsService::class)->dispatchDue();
        if ($count > 0) {
            $this->info("Queued {$count} campaign(s).");
        }

        return self::SUCCESS;
    }
}

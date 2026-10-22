<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Sms\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LaunchSmsCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $campaignId) {}

    public function handle(SmsService $sms): void
    {
        $campaign = SmsCampaign::find($this->campaignId);
        if ($campaign && in_array($campaign->status, ['queued', 'scheduled'], true)) {
            $sms->launch($campaign);
        }
    }
}

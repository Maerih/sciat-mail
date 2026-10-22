<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Sms\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public array $messageIds, public ?int $campaignId = null) {}

    public function handle(SmsService $sms): void
    {
        $campaign = $this->campaignId ? SmsCampaign::find($this->campaignId) : null;
        if ($this->campaignId && (! $campaign || $campaign->status !== 'sending')) {
            return; // paused or cancelled: queued rows stay queued
        }

        $messages = SmsMessage::with('provider', 'workspace')->whereIn('id', $this->messageIds)->where('status', 'queued')->orderBy('id')->get();
        foreach ($messages as $index => $message) {
            if ($campaign && $index % 20 === 0 && $index > 0 && SmsCampaign::whereKey($campaign->id)->value('status') !== 'sending') {
                return;
            }
            $sms->deliver($message);
        }

        $campaign && $sms->finishIfDone($campaign->refresh());
    }
}

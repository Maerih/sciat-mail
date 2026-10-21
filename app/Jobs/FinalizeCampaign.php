<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeCampaign implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (! $campaign || $campaign->status !== 'sending') {
            return;
        }

        $counts = $campaign->deliveries()->toBase()->selectRaw("
            sum(status = 'pending') as pending,
            sum(status in ('sent','bounced','complained')) as sent,
            sum(status = 'failed') as failed,
            sum(status = 'skipped') as skipped")->first();

        $campaign->forceFill([
            'sent_count' => (int) $counts->sent,
            'failed_count' => (int) $counts->failed,
            'skipped_count' => (int) $counts->skipped,
        ]);

        if ((int) $counts->pending === 0) {
            $campaign->forceFill(['status' => 'done', 'finished_at' => now()]);
        }

        $campaign->save();
    }
}

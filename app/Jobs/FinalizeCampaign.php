<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Webhooks;
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

        $finished = (int) $counts->pending === 0;
        if ($finished) {
            $campaign->forceFill(['status' => 'done', 'finished_at' => now()]);
        }

        $campaign->save();

        if ($finished) {
            Webhooks::dispatch($campaign->workspace_id, 'campaign.finished', [
                'uid' => $campaign->uid, 'name' => $campaign->name, 'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count, 'skipped' => $campaign->skipped_count, 'finished_at' => $campaign->finished_at->toIso8601String(),
            ]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Delivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots the campaign's recipients into the delivery ledger (deduplicated by email,
 * suppressed addresses removed) and fans out send batches.
 */
class LaunchCampaign implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 1800;

    public const BATCH_SIZE = 200;

    public function __construct(public int $campaignId) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(): void
    {
        $campaign = Campaign::with('audiences.segment', 'workspace')->find($this->campaignId);
        if (! $campaign || $campaign->status !== 'queued') {
            return;
        }

        $campaign->forceFill(['status' => 'sending', 'started_at' => $campaign->started_at ?? now()])->save();

        // Snapshot only once: a resumed campaign keeps its original ledger
        if (! $campaign->deliveries()->exists()) {
            $campaign->recipientQuery()
                ->select('subscribers.id', 'subscribers.email')
                ->orderBy('subscribers.id')
                ->chunkById(2000, function ($rows) use ($campaign) {
                    $now = now();
                    DB::table('deliveries')->insertOrIgnore($rows->map(fn ($r) => [
                        'uid' => Delivery::newUid(),
                        'campaign_id' => $campaign->id,
                        'subscriber_id' => $r->id,
                        'workspace_id' => $campaign->workspace_id,
                        'email' => $r->email,
                        'kind' => 'campaign',
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                }, 'subscribers.id', 'id');

            $campaign->forceFill(['recipients_count' => $campaign->deliveries()->count()])->save();
        }

        static::dispatchBatches($campaign);
    }

    public static function dispatchBatches(Campaign $campaign, int $delay = 0): void
    {
        $dispatched = false;
        $campaign->deliveries()->where('status', 'pending')->select('id')->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($rows) use ($campaign, $delay, &$dispatched) {
                SendCampaignBatch::dispatch($campaign->id, $rows->pluck('id')->all())->delay($delay);
                $dispatched = true;
            });

        if (! $dispatched) {
            FinalizeCampaign::dispatch($campaign->id);
        }
    }
}

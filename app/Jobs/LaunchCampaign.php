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
class LaunchCampaign implements ShouldBeUnique, ShouldQueue
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
        $firstLaunch = ! $campaign->deliveries()->exists();

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

        if ($campaign->isAbTest() && ! $campaign->winner_variant_id) {
            if ($firstLaunch) {
                static::assignTestSample($campaign);
            }
            if ($campaign->deliveries()->where('status', 'pending')->whereNull('variant_id')->exists()) {
                // Test phase: only the sample goes out until a winner is chosen
                $campaign->forceFill(['status' => 'testing', 'test_ends_at' => $campaign->test_ends_at
                    ?? now()->addHours((int) $campaign->abSetting('wait_hours'))])->save();
                static::dispatchBatches($campaign, onlyAssigned: true);
                PickAbWinner::dispatch($campaign->id)->delay($campaign->test_ends_at);

                return;
            }
        }

        static::dispatchBatches($campaign);
    }

    /**
     * Randomly picks test_percent of the recipients and spreads them evenly over the variants.
     * When the sample would cover everyone, every recipient is split and no winner phase follows.
     */
    public static function assignTestSample(Campaign $campaign): void
    {
        $variants = $campaign->variants()->pluck('id')->all();
        $total = $campaign->deliveries()->count();
        if (! $variants || ! $total) {
            return;
        }

        $sample = (int) max(count($variants), ceil($total * (int) $campaign->abSetting('test_percent') / 100));
        $ids = $campaign->deliveries()->inRandomOrder()->limit(min($sample, $total))->pluck('id');

        foreach ($ids->chunk(1000) as $chunk) {
            foreach ($chunk->values() as $i => $id) {
                $groups[$variants[$i % count($variants)]][] = $id;
            }
            foreach ($groups ?? [] as $variantId => $deliveryIds) {
                Delivery::whereIn('id', $deliveryIds)->update(['variant_id' => $variantId]);
            }
            $groups = [];
        }
    }

    public static function dispatchBatches(Campaign $campaign, int $delay = 0, bool $onlyAssigned = false): void
    {
        $dispatched = false;
        $campaign->deliveries()->where('status', 'pending')
            ->when($onlyAssigned, fn ($q) => $q->whereNotNull('variant_id'))
            ->select('id')->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($rows) use ($campaign, $delay, &$dispatched) {
                SendCampaignBatch::dispatch($campaign->id, $rows->pluck('id')->all())->delay($delay);
                $dispatched = true;
            });

        if (! $dispatched && ! $onlyAssigned) {
            FinalizeCampaign::dispatch($campaign->id);
        }
    }
}

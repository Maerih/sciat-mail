<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PickAbWinner implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(CampaignService $service): void
    {
        $campaign = Campaign::find($this->campaignId);
        // Paused tests or early manual picks are handled elsewhere
        if ($campaign && $campaign->status === 'testing' && $campaign->test_ends_at?->isPast()) {
            $service->pickWinner($campaign);
        }
    }
}

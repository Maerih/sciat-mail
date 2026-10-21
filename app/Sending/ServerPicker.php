<?php

namespace App\Sending;

use App\Models\Campaign;
use App\Models\SendingServer;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Chooses a sending server for each message: weighted random over the campaign's pool,
 * skipping inactive servers and servers that have used up their quota.
 */
class ServerPicker
{
    public function __construct(protected Throttle $throttle) {}

    /** @return Collection<int, array{server: SendingServer, weight: int}> */
    public function pool(Workspace $workspace, ?array $weights = null): Collection
    {
        $servers = $workspace->availableServers()->where('status', 'active')->get();

        if (! empty($weights)) {
            $servers = $servers->filter(fn ($s) => isset($weights[$s->id]) && (int) $weights[$s->id] > 0);
        }

        return $servers->map(fn ($s) => ['server' => $s, 'weight' => max(1, (int) ($weights[$s->id] ?? 1))])->values();
    }

    public function pick(Collection $pool): ?SendingServer
    {
        $available = $pool->filter(fn ($entry) => $this->throttle->hasCapacity($entry['server']));
        if ($available->isEmpty()) {
            return null;
        }

        $roll = random_int(1, $available->sum('weight'));
        foreach ($available as $entry) {
            $roll -= $entry['weight'];
            if ($roll <= 0) {
                return $entry['server'];
            }
        }

        return $available->last()['server'];
    }

    public function forCampaign(Campaign $campaign): Collection
    {
        return $this->pool($campaign->workspace, $campaign->server_weights);
    }

    /** Shortest wait until any server in the pool regains capacity. */
    public function retryAfter(Collection $pool): int
    {
        return $pool->map(fn ($e) => $this->throttle->retryAfter($e['server']))->min() ?? 300;
    }
}

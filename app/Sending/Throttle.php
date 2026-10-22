<?php

namespace App\Sending;

use App\Models\SendingDomain;
use App\Models\SendingServer;
use Illuminate\Support\Facades\Cache;

/**
 * Per-server hourly/daily send counters (fixed windows) kept in the cache store.
 */
class Throttle
{
    public function hasCapacity(SendingServer $server): bool
    {
        $daily = $server->effectiveDailyLimit();

        return ($server->hourly_limit === null || $this->used($server, 'hour') < $server->hourly_limit)
            && ($daily === null || $this->used($server, 'day') < $daily);
    }

    public function hit(SendingServer $server): void
    {
        foreach (['hour' => 3700, 'day' => 86500] as $window => $ttl) {
            $key = $this->key($server, $window);
            Cache::add($key, 0, $ttl);
            Cache::increment($key);
        }
    }

    public function used(SendingServer $server, string $window): int
    {
        return (int) Cache::get($this->key($server, $window), 0);
    }

    /** Seconds until the earliest exhausted window resets. */
    public function retryAfter(SendingServer $server): int
    {
        $now = now()->getTimestamp();
        if ($server->hourly_limit !== null && $this->used($server, 'hour') >= $server->hourly_limit) {
            return 3600 - ($now % 3600) + 5;
        }

        return 86400 - ($now % 86400) + 5;
    }

    /** Domain warmup: one daily counter per sending domain, shared by every server. */
    public function domainHasCapacity(SendingDomain $domain): bool
    {
        $allowance = $domain->dailyAllowance();

        return $allowance === null || $this->domainUsed($domain) < $allowance;
    }

    public function hitDomain(SendingDomain $domain): void
    {
        $key = $this->domainKey($domain);
        Cache::add($key, 0, 86500);
        Cache::increment($key);
    }

    public function domainUsed(SendingDomain $domain): int
    {
        return (int) Cache::get($this->domainKey($domain), 0);
    }

    protected function domainKey(SendingDomain $domain): string
    {
        return "throttle:domain:{$domain->id}:day:".now()->format('Ymd');
    }

    protected function key(SendingServer $server, string $window): string
    {
        $bucket = now()->format($window === 'hour' ? 'YmdH' : 'Ymd');

        return "throttle:server:{$server->id}:{$window}:{$bucket}";
    }
}

<?php

namespace App\Sending;

use App\Models\SendingServer;
use Illuminate\Support\Facades\Cache;

/**
 * Per-server hourly/daily send counters (fixed windows) kept in the cache store.
 */
class Throttle
{
    public function hasCapacity(SendingServer $server): bool
    {
        return ($server->hourly_limit === null || $this->used($server, 'hour') < $server->hourly_limit)
            && ($server->daily_limit === null || $this->used($server, 'day') < $server->daily_limit);
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
        if ($server->hourly_limit !== null && $this->used($server, 'hour') >= $server->hourly_limit) {
            return 3600 - (time() % 3600) + 5;
        }

        return 86400 - (time() % 86400) + 5;
    }

    protected function key(SendingServer $server, string $window): string
    {
        $bucket = $window === 'hour' ? date('YmdH') : date('Ymd');

        return "throttle:server:{$server->id}:{$window}:{$bucket}";
    }
}

<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;

/** Queues outbound webhook calls for every active endpoint subscribed to an event. */
class Webhooks
{
    /** @param array<string, mixed> $data */
    public static function dispatch(int $workspaceId, string $event, array $data): void
    {
        $hooks = Webhook::where('workspace_id', $workspaceId)->where('active', true)->get()
            ->filter(fn (Webhook $w) => in_array($event, $w->events ?? [], true));

        foreach ($hooks as $hook) {
            static::deliver($hook, $event, $data);
        }
    }

    /** @param array<string, mixed> $data */
    public static function deliver(Webhook $hook, string $event, array $data): void
    {
        $delivery = $hook->deliveries()->create([
            'event' => $event,
            'payload' => ['event' => $event, 'occurred_at' => now()->toIso8601String(), 'data' => $data],
            'status' => 'pending',
        ]);
        DeliverWebhook::dispatch($delivery->id)->afterCommit();
    }
}

<?php

namespace App\Services;

use App\Models\Blacklist;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for engagement and feedback events from tracking endpoints
 * and provider webhooks.
 */
class EventRecorder
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function open(Delivery $delivery, ?string $ip, ?string $agent): void
    {
        $delivery->logEvent('open', ['ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 500)]);
        $now = now();
        Delivery::whereKey($delivery->id)->update([
            'open_count' => DB::raw('open_count + 1'),
            'opened_at' => $delivery->opened_at ?? $now,
        ]);
        $delivery->subscriber?->forceFill(['last_open_at' => $now])->saveQuietly();
    }

    public function click(Delivery $delivery, string $url, ?string $ip, ?string $agent): void
    {
        $delivery->logEvent('click', ['url' => mb_substr($url, 0, 2000), 'ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 500)]);
        $now = now();
        // a click implies an open even when images were blocked
        Delivery::whereKey($delivery->id)->update([
            'click_count' => DB::raw('click_count + 1'),
            'clicked_at' => $delivery->clicked_at ?? $now,
            'opened_at' => $delivery->opened_at ?? $now,
        ]);
        $delivery->subscriber?->forceFill(['last_click_at' => $now, 'last_open_at' => $now])->saveQuietly();
    }

    public function unsubscribe(Delivery $delivery, ?string $ip = null, ?string $agent = null, string $via = 'link'): void
    {
        if (! $delivery->events()->where('type', 'unsubscribe')->exists()) {
            $delivery->logEvent('unsubscribe', ['subtype' => $via, 'ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 500)]);
        }
        if ($delivery->subscriber && $delivery->subscriber->status !== 'unsubscribed') {
            $this->subscriptions->unsubscribe($delivery->subscriber);
        }
    }

    /** $type: hard | soft | blocked (blocked is treated like soft). */
    public function bounce(Delivery $delivery, string $type, ?string $detail = null): void
    {
        $delivery->logEvent('bounce', ['subtype' => $type, 'detail' => $detail]);

        if ($type === 'hard') {
            $delivery->forceFill(['status' => 'bounced'])->save();
            Blacklist::add($delivery->email, $delivery->workspace_id, 'hard_bounce', $detail ? mb_substr($detail, 0, 500) : null);
        }
    }

    public function complaint(Delivery $delivery, ?string $detail = null): void
    {
        $delivery->logEvent('complaint', ['detail' => $detail]);
        $delivery->forceFill(['status' => 'complained'])->save();
        if ($delivery->subscriber) {
            $delivery->subscriber->unsubscribe();
        }
        Blacklist::add($delivery->email, $delivery->workspace_id, 'complaint');
    }

    public function findDelivery(?string $uid, ?string $providerMessageId = null): ?Delivery
    {
        if ($uid && ($d = Delivery::where('uid', $uid)->first())) {
            return $d;
        }
        if ($providerMessageId) {
            $id = trim($providerMessageId, '<> ');

            return Delivery::where('provider_message_id', $id)->orWhere('provider_message_id', '<'.$id.'>')->first();
        }

        return null;
    }
}

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

    /** Device/OS/client from the user agent; country from a trusted CDN header when present. @return array<string, ?string> */
    protected function context(?string $agent): array
    {
        $country = strtoupper((string) request()?->header('CF-IPCountry', request()?->header('CloudFront-Viewer-Country', '')));

        return UserAgent::parse($agent) + ['country' => preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null];
    }

    /** @return array<string, mixed> */
    protected function payload(Delivery $delivery, array $extra = []): array
    {
        $campaign = $delivery->campaign;

        return [
            'delivery_uid' => $delivery->uid,
            'email' => $delivery->email,
            'campaign' => $campaign ? ['uid' => $campaign->uid, 'name' => $campaign->name, 'automation' => (bool) $campaign->automation_id] : null,
            'subscriber_uid' => $delivery->subscriber?->uid,
        ] + $extra;
    }

    public function open(Delivery $delivery, ?string $ip, ?string $agent): void
    {
        if ($delivery->opened_at === null) {
            Webhooks::dispatch($delivery->workspace_id, 'email.opened', $this->payload($delivery));
        }
        $delivery->logEvent('open', ['ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 500)] + $this->context($agent));
        $now = now();
        Delivery::whereKey($delivery->id)->update([
            'open_count' => DB::raw('open_count + 1'),
            'opened_at' => $delivery->opened_at ?? $now,
        ]);
        $delivery->subscriber?->forceFill(['last_open_at' => $now])->saveQuietly();
    }

    public function click(Delivery $delivery, string $url, ?string $ip, ?string $agent): void
    {
        $delivery->logEvent('click', ['url' => mb_substr($url, 0, 2000), 'ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 500)] + $this->context($agent));
        Webhooks::dispatch($delivery->workspace_id, 'email.clicked', $this->payload($delivery, ['url' => $url]));
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
        Webhooks::dispatch($delivery->workspace_id, 'email.bounced', $this->payload($delivery, ['bounce_type' => $type, 'detail' => $detail]));

        if ($type === 'hard') {
            $delivery->forceFill(['status' => 'bounced'])->save();
            Blacklist::add($delivery->email, $delivery->workspace_id, 'hard_bounce', $detail ? mb_substr($detail, 0, 500) : null);
        }
    }

    public function complaint(Delivery $delivery, ?string $detail = null): void
    {
        $delivery->logEvent('complaint', ['detail' => $detail]);
        Webhooks::dispatch($delivery->workspace_id, 'email.complained', $this->payload($delivery));
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

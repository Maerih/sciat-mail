<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * POSTs one event to a client endpoint, signed with HMAC-SHA256 of the raw body
 * (X-SM-Signature: sha256=...). Retries with backoff; endpoints that keep failing are disabled.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var int[] */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);
        $hook = $delivery?->webhook;
        if (! $hook || ! $hook->active) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $delivery->increment('attempts');

        try {
            $response = Http::timeout(10)->withBody($body, 'application/json')->withHeaders([
                'User-Agent' => config('app.name').' Webhooks',
                'X-SM-Event' => $delivery->event,
                'X-SM-Delivery' => (string) $delivery->id,
                'X-SM-Signature' => $hook->sign($body),
            ])->post($hook->url);
            $status = $response->status();
            $ok = $response->successful();
            $responseBody = mb_substr($response->body(), 0, 1000);
        } catch (\Throwable $e) {
            [$status, $ok, $responseBody] = [null, false, mb_substr($e->getMessage(), 0, 1000)];
        }

        $delivery->forceFill(['response_status' => $status, 'response_body' => $responseBody, 'status' => $ok ? 'success' : 'pending'])->save();

        if ($ok) {
            $hook->forceFill(['failures' => 0])->save();

            return;
        }

        if ($this->attempts() >= $this->tries) {
            $delivery->forceFill(['status' => 'failed'])->save();
            $hook->increment('failures');
            if ($hook->failures >= Webhook::DISABLE_AFTER) {
                $hook->forceFill(['active' => false])->save();
            }

            return;
        }

        $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
    }
}

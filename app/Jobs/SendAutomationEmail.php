<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Sending\Dispatcher;
use App\Sending\MessageBuilder;
use App\Sending\SendFailed;
use App\Sending\ServerPicker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Delivers one automation email through the workspace's server pool. */
class SendAutomationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public const MAX_ATTEMPTS = 3;

    public function __construct(public int $deliveryId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function handle(ServerPicker $picker, MessageBuilder $builder, Dispatcher $dispatcher): void
    {
        $delivery = Delivery::with('campaign.workspace', 'subscriber.list.fields', 'workspace')->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending' || ! $delivery->campaign) {
            return;
        }

        $campaign = $delivery->campaign;
        $pool = $picker->forCampaign($campaign);
        $server = $picker->pick($pool);
        if (! $server) {
            if ($pool->isEmpty()) {
                $delivery->forceFill(['status' => 'failed', 'error' => 'No active sending server is available.'])->save();

                return;
            }
            $this->release($picker->retryAfter($pool));

            return;
        }

        $delivery->forceFill(['sending_server_id' => $server->id, 'attempts' => $delivery->attempts + 1]);

        try {
            $message = $builder->forCampaign($campaign, $delivery, $delivery->subscriber, $server);
            $messageId = $dispatcher->send($message, $server, $campaign->from_email, $delivery->email);
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $messageId, 'error' => null])->save();
        } catch (SendFailed $e) {
            $retry = $e->transient && $delivery->attempts < self::MAX_ATTEMPTS;
            $delivery->forceFill(['status' => $retry ? 'pending' : 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            if ($retry) {
                $this->release(300);
            }
        }
    }
}

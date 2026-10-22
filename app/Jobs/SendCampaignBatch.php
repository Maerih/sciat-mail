<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\SendingDomain;
use App\Sending\Dispatcher;
use App\Sending\MessageBuilder;
use App\Sending\SendFailed;
use App\Sending\ServerPicker;
use App\Sending\Throttle;
use App\Services\Quota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 0; // releases for throttling/windows are not failures

    public const MAX_ATTEMPTS = 3;

    public function __construct(public int $campaignId, public array $deliveryIds) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(7);
    }

    public function handle(ServerPicker $picker, MessageBuilder $builder, Dispatcher $dispatcher): void
    {
        $campaign = Campaign::with('workspace')->find($this->campaignId);
        if (! $campaign || ! in_array($campaign->status, ['sending', 'testing'], true)) {
            return; // paused or cancelled: pending rows are picked up again on resume
        }
        if ($campaign->workspace->isSuspended()) {
            $campaign->forceFill(['status' => 'paused', 'last_error' => 'Paused: sending is suspended for this client.'])->save();

            return;
        }
        $variants = $campaign->isAbTest() ? $campaign->variants()->get()->keyBy('id') : collect();

        if (! $campaign->inSendingWindow()) {
            $this->release($campaign->secondsUntilWindowOpens());

            return;
        }

        $pool = $picker->forCampaign($campaign);
        if ($pool->isEmpty()) {
            $campaign->forceFill(['status' => 'error', 'last_error' => 'No active sending server is available for this campaign.'])->save();

            return;
        }

        $remaining = Quota::emailsRemaining($campaign->workspace);
        $domain = SendingDomain::forEmail((string) $campaign->from_email, $campaign->workspace_id);
        $domain = $domain?->isWarmingUp() ? $domain : null;
        $throttle = app(Throttle::class);

        $deliveries = Delivery::with('subscriber.list.fields', 'workspace', 'campaign')
            ->whereIn('id', $this->deliveryIds)->where('status', 'pending')->orderBy('id')->get();

        foreach ($deliveries as $index => $delivery) {
            if ($index % 25 === 0 && $index > 0 && ! in_array(Campaign::whereKey($campaign->id)->value('status'), ['sending', 'testing'], true)) {
                return;
            }

            if ($remaining !== null && $remaining <= 0) {
                $campaign->forceFill(['status' => 'paused', 'last_error' => 'The client\'s monthly email limit was reached. Raise the limit or resume next month.'])->save();

                return;
            }

            if ($domain && ! $throttle->domainHasCapacity($domain)) {
                // the sending domain used up today's warmup allowance: continue tomorrow
                $this->release(86400 - (now()->getTimestamp() % 86400) + 60);

                return;
            }

            $server = $picker->pick($pool);
            if (! $server) {
                // all servers hit their quota: retry the remainder when capacity returns
                $this->release($picker->retryAfter($pool));

                return;
            }

            if (! $delivery->subscriber || ! $delivery->subscriber->isSendable()) {
                $delivery->forceFill(['status' => 'skipped', 'error' => 'Subscriber is no longer subscribed'])->save();

                continue;
            }

            $delivery->forceFill(['sending_server_id' => $server->id, 'attempts' => $delivery->attempts + 1]);

            try {
                $version = $campaign->withVariant($variants->get($delivery->variant_id));
                $message = $builder->forCampaign($version, $delivery, $delivery->subscriber, $server);
                $messageId = $dispatcher->send($message, $server, $campaign->from_email, $delivery->email);
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $messageId, 'error' => null])->save();
                $domain && $throttle->hitDomain($domain);
                if ($remaining !== null) {
                    $remaining--;
                }
            } catch (SendFailed $e) {
                $retry = $e->transient && $delivery->attempts < self::MAX_ATTEMPTS;
                $delivery->forceFill(['status' => $retry ? 'pending' : 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

                if (! $retry && ! $campaign->skip_failed) {
                    $campaign->forceFill(['status' => 'error', 'last_error' => $e->getMessage()])->save();

                    return;
                }
            }
        }

        $retryable = Delivery::whereIn('id', $this->deliveryIds)->where('status', 'pending')->pluck('id')->all();
        if ($retryable) {
            self::dispatch($campaign->id, $retryable)->delay(now()->addMinutes(5));

            return;
        }

        FinalizeCampaign::dispatch($campaign->id);
    }
}

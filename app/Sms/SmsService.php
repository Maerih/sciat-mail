<?php

namespace App\Sms;

use App\Jobs\LaunchSmsCampaign;
use App\Jobs\SendSmsBatch;
use App\Models\MailList;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Quota;
use App\Services\Webhooks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SmsService
{
    public const OPT_OUT_KEYWORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'ACHA', 'SITISHA'];

    public const OPT_IN_KEYWORDS = ['START', 'UNSTOP', 'ANZA'];

    public const BATCH = 100;

    /** Sends one queued message now. Returns false when it failed. */
    public function deliver(SmsMessage $message): bool
    {
        $provider = $message->provider ?? SmsProvider::defaultFor($message->workspace);
        if (! $provider || $provider->status !== 'active') {
            $message->forceFill(['status' => 'failed', 'error' => 'No active SMS provider.'])->save();

            return false;
        }

        // Respect the provider's messages-per-second limit
        while (! RateLimiter::attempt('sms-rate:'.$provider->id, max(1, $provider->per_second), fn () => true, 1)) {
            usleep(200_000);
        }

        try {
            $result = SmsManager::driver($provider->type)->send($provider, $message->phone, $message->body, $message->sender ?: $provider->sender_id);
        } catch (\Throwable $e) {
            $result = SendResult::failed($e->getMessage());
        }

        $message->forceFill([
            'sms_provider_id' => $provider->id,
            'sender' => $message->sender ?: $provider->sender_id,
            'status' => $result->ok ? $result->status : 'failed',
            'provider_message_id' => $result->messageId,
            'error' => $result->error,
            'sent_at' => $result->ok ? now() : null,
            'delivered_at' => $result->ok && $result->status === 'delivered' ? now() : null,
        ])->save();

        return $result->ok;
    }

    /** Queues a single message (API, automation, inbox reply). */
    public function queue(Workspace $workspace, string $phone, string $body, string $source, ?Subscriber $subscriber = null, ?SmsProvider $provider = null, ?string $sender = null): SmsMessage
    {
        $message = SmsMessage::create([
            'workspace_id' => $workspace->id, 'subscriber_id' => $subscriber?->id, 'sms_provider_id' => $provider?->id,
            'direction' => 'out', 'source' => $source, 'phone' => $phone, 'sender' => $sender, 'body' => $body, 'parts' => SmsText::parts($body),
        ]);
        SendSmsBatch::dispatch([$message->id]);

        return $message;
    }

    /** Creates one message per recipient and queues them in batches. Returns how many were queued. */
    public function launch(SmsCampaign $campaign): int
    {
        if ($listId = $campaign->audienceValue('list_id')) {
            $list = MailList::find($listId);
            $list && $this->syncPhones($list);
        }

        $remaining = Quota::smsRemaining($campaign->workspace);
        $template = $campaign->fullMessage();
        $seen = [];
        $count = 0;

        $campaign->recipientQuery()->with('list.fields')->orderBy('id')->chunkById(500, function ($subscribers) use ($campaign, $template, &$seen, &$count, $remaining) {
            $rows = [];
            foreach ($subscribers as $subscriber) {
                if (isset($seen[$subscriber->phone]) || ($remaining !== null && $count >= $remaining)) {
                    continue; // one message per phone number, and never over the monthly limit
                }
                $seen[$subscriber->phone] = true;
                $body = SmsText::render($template, $subscriber);
                $rows[] = [
                    'uid' => Str::lower(Str::random(13)), 'workspace_id' => $campaign->workspace_id,
                    'sms_campaign_id' => $campaign->id, 'sms_provider_id' => $campaign->sms_provider_id, 'subscriber_id' => $subscriber->id,
                    'direction' => 'out', 'source' => 'campaign', 'phone' => $subscriber->phone, 'sender' => $campaign->sender_id,
                    'body' => $body, 'parts' => SmsText::parts($body), 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
                ];
                $count++;
            }
            if ($rows) {
                SmsMessage::insert($rows);
            }
        });

        $campaign->forceFill(['status' => $count ? 'sending' : 'done', 'started_at' => now(), 'finished_at' => $count ? null : now(),
            'last_error' => $count ? null : 'No recipients with a phone number matched.'])->save();

        $campaign->messages()->where('status', 'queued')->orderBy('id')->pluck('id')->chunk(self::BATCH)
            ->each(fn ($ids) => SendSmsBatch::dispatch($ids->values()->all(), $campaign->id));

        return $count;
    }

    public function dispatchDue(): int
    {
        $due = SmsCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn (SmsCampaign $campaign) => LaunchSmsCampaign::dispatch($campaign->id));

        return $due->count();
    }

    public function finishIfDone(SmsCampaign $campaign): void
    {
        if ($campaign->status === 'sending' && ! $campaign->messages()->where('status', 'queued')->exists()) {
            $campaign->forceFill(['status' => 'done', 'finished_at' => now()])->save();
        }
    }

    /** Fills subscribers.phone (E.164) from the list's phone field — covers rows written by bulk imports. */
    public function syncPhones(MailList $list): int
    {
        $tag = $list->phoneTag();
        if (! $tag) {
            return 0;
        }
        $country = $list->workspace->sms_country;
        $updated = 0;
        $list->subscribers()->whereNull('phone')->whereNotNull('data->'.$tag)->select('id', 'data')->chunkById(1000, function ($subscribers) use ($tag, $country, &$updated) {
            foreach ($subscribers as $subscriber) {
                if ($phone = PhoneNumber::normalize($subscriber->data[$tag] ?? null, $country)) {
                    DB::table('subscribers')->where('id', $subscriber->id)->update(['phone' => $phone]);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    /** @param array<int, array{id: string, status: string, error: ?string}> $updates */
    public function applyStatusUpdates(SmsProvider $provider, array $updates): int
    {
        $count = 0;
        foreach ($updates as $update) {
            $message = SmsMessage::where('sms_provider_id', $provider->id)->where('provider_message_id', $update['id'])->first();
            if (! $message || $message->status === 'delivered') {
                continue;
            }
            $message->forceFill([
                'status' => $update['status'],
                'error' => $update['status'] === 'failed' ? ($update['error'] ?? 'Not delivered') : null,
                'delivered_at' => $update['status'] === 'delivered' ? now() : $message->delivered_at,
            ])->save();
            if (in_array($update['status'], ['delivered', 'failed'], true)) {
                Webhooks::dispatch($message->workspace_id, 'sms.'.$update['status'], $this->payload($message));
            }
            $count++;
        }

        return $count;
    }

    /** @param array{from: string, to: ?string, text: string, id: ?string} $data */
    public function receive(SmsProvider $provider, array $data): ?SmsMessage
    {
        $phone = PhoneNumber::normalize($data['from']);
        if (! $phone) {
            return null;
        }
        // Shared providers serve every client: attribute the reply to whoever last texted this number
        $workspaceId = $provider->workspace_id
            ?? SmsMessage::where('sms_provider_id', $provider->id)->where('phone', $phone)->where('direction', 'out')->latest('id')->value('workspace_id');
        if (! $workspaceId) {
            return null;
        }
        $subscriberIds = Subscriber::whereIn('mail_list_id', MailList::where('workspace_id', $workspaceId)->select('id'))->where('phone', $phone)->pluck('id');

        $message = SmsMessage::create([
            'workspace_id' => $workspaceId, 'sms_provider_id' => $provider->id, 'subscriber_id' => $subscriberIds->first(),
            'direction' => 'in', 'source' => 'inbound', 'phone' => $phone, 'sender' => $data['to'], 'body' => $data['text'],
            'parts' => SmsText::parts($data['text']), 'status' => 'received', 'provider_message_id' => $data['id'],
        ]);

        $keyword = strtoupper(trim(preg_replace('/[^\pL\s]/u', '', $data['text'])));
        if (in_array($keyword, self::OPT_OUT_KEYWORDS, true)) {
            Subscriber::whereIn('id', $subscriberIds)->whereNull('sms_opted_out_at')->update(['sms_opted_out_at' => now()]);
        } elseif (in_array($keyword, self::OPT_IN_KEYWORDS, true)) {
            Subscriber::whereIn('id', $subscriberIds)->update(['sms_opted_out_at' => null]);
        }
        Webhooks::dispatch($workspaceId, 'sms.received', $this->payload($message));

        return $message;
    }

    /** @return array<string, mixed> */
    public function payload(SmsMessage $message): array
    {
        return ['id' => $message->uid, 'phone' => $message->phone, 'direction' => $message->direction, 'status' => $message->status,
            'body' => $message->body, 'error' => $message->error, 'campaign' => $message->campaign?->uid];
    }
}

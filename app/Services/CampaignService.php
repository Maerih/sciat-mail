<?php

namespace App\Services;

use App\Jobs\LaunchCampaign;
use App\Models\Campaign;
use App\Models\CampaignVariant;
use App\Models\SendingDomain;
use App\Sending\ServerPicker;

class CampaignService
{
    public function __construct(protected ServerPicker $picker) {}

    /**
     * Pre-flight checklist shown on the confirm step. 'error' items block launch.
     *
     * @return array<int, array{key: string, label: string, level: string, message: string, step: ?string}>
     */
    public function preflight(Campaign $campaign): array
    {
        $checks = [];
        $add = function ($key, $label, $level, $message, $step = null) use (&$checks) {
            $checks[] = compact('key', 'label', 'level', 'message', 'step');
        };

        $recipients = $campaign->audiences->where('exclude', false)->isEmpty() ? 0 : $campaign->estimateRecipients();
        $recipients > 0
            ? $add('recipients', 'Recipients', 'ok', number_format($recipients).' subscribers will receive this campaign', 'recipients')
            : $add('recipients', 'Recipients', 'error', 'No subscribed recipients match the selected lists/segments', 'recipients');

        $remaining = Quota::emailsRemaining($campaign->workspace);
        if ($remaining !== null && $recipients > 0) {
            $recipients > $remaining
                ? $add('quota', 'Monthly email limit', 'error', number_format($recipients).' recipients but only '.number_format($remaining).' emails left this month', null)
                : $add('quota', 'Monthly email limit', 'ok', number_format($remaining - $recipients).' emails left this month after this send', null);
        }

        filled($campaign->subject)
            ? $add('subject', 'Subject line', 'ok', '“'.$campaign->subject.'”', 'setup')
            : $add('subject', 'Subject line', 'error', 'Add a subject line', 'setup');

        if (blank($campaign->from_email)) {
            $add('sender', 'Sender', 'error', 'Add a from email address', 'setup');
        } else {
            $domain = SendingDomain::forEmail($campaign->from_email, $campaign->workspace_id);
            match (true) {
                $domain?->isVerified() => $add('sender', 'Sender', 'ok', $campaign->from_name.' <'.$campaign->from_email.'> — domain verified', 'setup'),
                (bool) $domain => $add('sender', 'Sender', 'warning', 'The sender domain is added but DNS is not fully verified yet', 'setup'),
                default => $add('sender', 'Sender', 'warning', 'The sender domain is not authenticated. Add it under Sending → Domains to improve inbox placement.', 'setup'),
            };
        }

        if ($campaign->isAbTest()) {
            $variants = $campaign->variants;
            $variants->count() >= 2
                ? $add('variants', 'A/B variants', 'ok', $variants->count().' variants · '.$campaign->abSetting('test_percent').'% test group · winner by '
                    .$campaign->abSetting('metric').' after '.$campaign->abSetting('wait_hours').'h', 'variants')
                : $add('variants', 'A/B variants', 'error', 'Add at least two variants to compare', 'variants');
        }

        $content = $campaign->isPlain() ? $campaign->plain : $campaign->html;
        filled(trim(strip_tags((string) $content, '<img>')))
            ? $add('content', 'Content', 'ok', $campaign->isPlain() ? 'Plain text email' : 'HTML email', 'content')
            : $add('content', 'Content', 'error', 'The email has no content yet', 'content');

        $pool = $this->picker->forCampaign($campaign);
        $pool->isNotEmpty()
            ? $add('servers', 'Sending servers', 'ok', $pool->map(fn ($e) => $e['server']->name)->implode(', '), 'setup')
            : $add('servers', 'Sending servers', 'error', 'No active sending server is available', 'setup');

        $campaign->scheduled_at && $campaign->scheduled_at->isFuture()
            ? $add('schedule', 'Delivery schedule', 'ok', 'Scheduled for '.$campaign->scheduled_at->setTimezone($campaign->workspace->timezone)->format('D j M Y, H:i T'), 'schedule')
            : $add('schedule', 'Delivery schedule', 'ok', 'Sends immediately after launch'.($campaign->sending_window ? ' (within the sending window)' : ''), 'schedule');

        return $checks;
    }

    public function canLaunch(Campaign $campaign): bool
    {
        return collect($this->preflight($campaign))->where('level', 'error')->isEmpty();
    }

    public function launch(Campaign $campaign): void
    {
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            $campaign->forceFill(['status' => 'scheduled'])->save();

            return;
        }

        $this->queue($campaign);
    }

    public function queue(Campaign $campaign): void
    {
        $campaign->forceFill(['status' => 'queued', 'last_error' => null])->save();
        LaunchCampaign::dispatch($campaign->id);
    }

    public function pause(Campaign $campaign): void
    {
        if ($campaign->isRunning() || $campaign->status === 'scheduled') {
            $campaign->forceFill(['status' => 'paused'])->save();
        }
    }

    public function resume(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['paused', 'error'], true)) {
            $this->queue($campaign);
        }
    }

    public function cancel(Campaign $campaign): void
    {
        $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        $campaign->deliveries()->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Campaign cancelled']);
    }

    /**
     * Ends an A/B test: the variant with the best open (or click) rate wins — or the one chosen
     * manually — and every recipient outside the test group gets the winning version.
     */
    public function pickWinner(Campaign $campaign, ?CampaignVariant $variant = null): CampaignVariant
    {
        if (! $variant) {
            $metric = $campaign->abSetting('metric') === 'clicks' ? 'click_rate' : 'open_rate';
            $stats = $campaign->variantStats();
            $variant = $campaign->variants->sortBy([
                fn ($a, $b) => $stats[$b->id][$metric] <=> $stats[$a->id][$metric],
                fn ($a, $b) => $stats[$b->id]['opened'] <=> $stats[$a->id]['opened'],
                fn ($a, $b) => $a->id <=> $b->id,
            ])->first();
        }

        $campaign->deliveries()->where('status', 'pending')->whereNull('variant_id')->update(['variant_id' => $variant->id]);
        $campaign->forceFill(['winner_variant_id' => $variant->id, 'test_ends_at' => $campaign->test_ends_at ?? now()])->save();

        if ($campaign->status === 'testing') {
            $campaign->forceFill(['status' => 'sending'])->save();
            LaunchCampaign::dispatchBatches($campaign);
        }

        return $variant;
    }

    /** Called every minute by the scheduler: starts due campaigns and ends due A/B tests. */
    public function dispatchDue(): int
    {
        $due = Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn (Campaign $c) => $this->queue($c));

        Campaign::where('status', 'testing')->whereNull('winner_variant_id')->where('test_ends_at', '<=', now())
            ->get()->each(fn (Campaign $c) => $this->pickWinner($c));

        return $due->count();
    }
}

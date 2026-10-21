<?php

namespace App\Services;

use App\Jobs\LaunchCampaign;
use App\Models\Campaign;
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

    /** Called every minute by the scheduler. */
    public function dispatchDue(): int
    {
        $due = Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn (Campaign $c) => $this->queue($c));

        return $due->count();
    }
}

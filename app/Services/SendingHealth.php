<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\Setting;
use App\Models\Workspace;

/**
 * Watches each client's hard-bounce and complaint rates over a rolling window. In
 * "monitor" mode it only flags problems; in "enforce" mode it suspends the client's
 * sending and pauses running campaigns before the sending servers' reputation suffers.
 */
class SendingHealth
{
    public const MODES = ['off' => 'Off', 'monitor' => 'Monitor only', 'enforce' => 'Monitor and enforce'];

    /** Setting key => default. Rates are percentages. */
    public const DEFAULTS = [
        'health_mode' => 'monitor',
        'health_bounce_warn' => 3,
        'health_bounce_suspend' => 8,
        'health_complaint_warn' => 0.1,
        'health_complaint_suspend' => 0.3,
        'health_window_days' => 7,
        'health_min_sent' => 200,
    ];

    public static function config(string $key): string|float|int
    {
        $value = Setting::get($key, static::DEFAULTS[$key]);

        return $key === 'health_mode' ? (string) $value : $value + 0;
    }

    public static function mode(): string
    {
        return static::config('health_mode');
    }

    /**
     * @return array{sent: int, bounces: int, complaints: int, bounce_rate: float, complaint_rate: float, level: string, reason: ?string}
     */
    public static function measure(Workspace $workspace): array
    {
        $since = now()->subDays((int) static::config('health_window_days'));
        if ($workspace->health_reviewed_at && $workspace->health_reviewed_at->greaterThan($since)) {
            $since = $workspace->health_reviewed_at;
        }

        $sent = Delivery::where('workspace_id', $workspace->id)->whereIn('kind', ['campaign', 'automation'])->where('sent_at', '>=', $since)->count();
        $events = DeliveryEvent::query()
            ->whereIn('delivery_id', Delivery::where('workspace_id', $workspace->id)->select('id'))
            ->where('created_at', '>=', $since)
            ->where(fn ($q) => $q->where('type', 'complaint')->orWhere(fn ($q) => $q->where('type', 'bounce')->where(fn ($q) => $q->whereNull('subtype')->orWhere('subtype', '!=', 'soft'))))
            ->selectRaw("sum(type = 'bounce') as bounces, sum(type = 'complaint') as complaints")->first();

        $bounces = (int) $events?->bounces;
        $complaints = (int) $events?->complaints;
        $bounceRate = $sent ? round($bounces / $sent * 100, 2) : 0.0;
        $complaintRate = $sent ? round($complaints / $sent * 100, 3) : 0.0;

        [$level, $reason] = match (true) {
            $sent < static::config('health_min_sent') => ['ok', null],
            $bounceRate >= static::config('health_bounce_suspend') => ['suspend', "Hard bounce rate {$bounceRate}% is above the ".static::config('health_bounce_suspend').'% limit'],
            $complaintRate >= static::config('health_complaint_suspend') => ['suspend', "Complaint rate {$complaintRate}% is above the ".static::config('health_complaint_suspend').'% limit'],
            $bounceRate >= static::config('health_bounce_warn') => ['warn', "Hard bounce rate {$bounceRate}% is above the ".static::config('health_bounce_warn').'% warning level'],
            $complaintRate >= static::config('health_complaint_warn') => ['warn', "Complaint rate {$complaintRate}% is above the ".static::config('health_complaint_warn').'% warning level'],
            default => ['ok', null],
        };

        return ['sent' => $sent, 'bounces' => $bounces, 'complaints' => $complaints, 'bounce_rate' => $bounceRate, 'complaint_rate' => $complaintRate, 'level' => $level, 'reason' => $reason];
    }

    /** Measures every workspace; suspends the ones over the limit when enforcing. Returns how many were suspended. */
    public static function checkAll(): int
    {
        if (static::mode() === 'off') {
            return 0;
        }

        $suspended = 0;
        foreach (Workspace::whereNull('sending_suspended_at')->get() as $workspace) {
            $health = static::measure($workspace);
            if ($health['level'] === 'suspend' && static::mode() === 'enforce') {
                static::suspend($workspace, $health['reason']);
                $suspended++;
            }
        }

        return $suspended;
    }

    public static function suspend(Workspace $workspace, string $reason): void
    {
        $workspace->forceFill(['sending_suspended_at' => now(), 'suspension_reason' => $reason])->save();
        Campaign::where('workspace_id', $workspace->id)->whereIn('status', ['sending', 'queued'])
            ->update(['status' => 'paused', 'last_error' => 'Paused automatically: '.$reason.'.']);
    }

    /** Lifts a suspension; earlier events no longer count so the client starts with a clean window. */
    public static function lift(Workspace $workspace): void
    {
        $workspace->forceFill(['sending_suspended_at' => null, 'suspension_reason' => null, 'health_reviewed_at' => now()])->save();
    }
}

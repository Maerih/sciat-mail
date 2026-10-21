<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    use BelongsToWorkspace, HasFactory, HasUid;

    public const STATUSES = ['draft', 'scheduled', 'queued', 'testing', 'sending', 'paused', 'done', 'cancelled', 'error', 'automation'];

    /** @var array{test_percent: int, metric: string, wait_hours: int} */
    public const AB_DEFAULTS = ['test_percent' => 20, 'metric' => 'opens', 'wait_hours' => 4];

    protected $fillable = [
        'workspace_id', 'automation_id', 'name', 'type', 'subject', 'preheader', 'from_name', 'from_email', 'reply_to', 'html', 'plain',
        'track_open', 'track_click', 'sign_dkim', 'skip_failed', 'spintax', 'server_weights', 'utm', 'scheduled_at', 'sending_window', 'ab_config',
    ];

    protected function casts(): array
    {
        return [
            'track_open' => 'boolean',
            'track_click' => 'boolean',
            'sign_dkim' => 'boolean',
            'skip_failed' => 'boolean',
            'spintax' => 'boolean',
            'server_weights' => 'array',
            'utm' => 'array',
            'sending_window' => 'array',
            'ab_config' => 'array',
            'test_ends_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** Excludes the hidden campaigns that hold automation emails. */
    public function scopeRegular(Builder $query): Builder
    {
        return $query->whereNull('automation_id');
    }

    public function isAutomationEmail(): bool
    {
        return $this->automation_id !== null;
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CampaignVariant::class)->orderBy('label');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(CampaignVariant::class, 'winner_variant_id');
    }

    public function isAbTest(): bool
    {
        return $this->type === 'ab';
    }

    public function abSetting(string $key): int|string
    {
        return ($this->ab_config ?? [])[$key] ?? self::AB_DEFAULTS[$key];
    }

    /**
     * Unsaved copy of the campaign with a variant's overrides applied, used to build the
     * message for recipients assigned to that variant.
     */
    public function withVariant(?CampaignVariant $variant): self
    {
        if (! $variant) {
            return $this;
        }

        $copy = clone $this;
        foreach (['subject', 'from_name', 'preheader', 'html'] as $field) {
            if (filled($variant->{$field})) {
                $copy->{$field} = $variant->{$field};
            }
        }
        if (filled($variant->html)) {
            $copy->plain = null; // regenerate text from the variant's HTML
        }

        return $copy;
    }

    /** Opens/clicks per variant over the deliveries sent with it. @return array<int, array<string, int|float>> */
    public function variantStats(): array
    {
        $rows = $this->deliveries()->toBase()->whereNotNull('variant_id')->whereNotNull('sent_at')
            ->selectRaw('variant_id, count(*) as sent, sum(opened_at is not null) as opened, sum(clicked_at is not null) as clicked')
            ->groupBy('variant_id')->get()->keyBy('variant_id');

        return $this->variants->mapWithKeys(function (CampaignVariant $v) use ($rows) {
            $r = $rows->get($v->id);
            $sent = (int) ($r->sent ?? 0);

            return [$v->id => [
                'sent' => $sent,
                'opened' => (int) ($r->opened ?? 0),
                'clicked' => (int) ($r->clicked ?? 0),
                'open_rate' => $sent ? round($r->opened / $sent * 100, 2) : 0.0,
                'click_rate' => $sent ? round($r->clicked / $sent * 100, 2) : 0.0,
            ]];
        })->all();
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'paused'], true);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'testing', 'sending'], true);
    }

    public function isPlain(): bool
    {
        return $this->type === 'plain';
    }

    /**
     * Subscribed, non-suppressed subscribers matching the included audiences minus the
     * excluded ones. Duplicate emails across lists are collapsed when the snapshot is taken.
     */
    public function recipientQuery(): Builder
    {
        $includes = $this->audiences->where('exclude', false);
        $excludes = $this->audiences->where('exclude', true);
        $workspaceId = $this->workspace_id;

        $query = Subscriber::query()
            ->where('subscribers.status', 'subscribed')
            ->whereIn('mail_list_id', MailList::where('workspace_id', $workspaceId)->select('id'))
            ->whereNotExists(fn ($q) => $q->from('blacklists')
                ->whereColumn('blacklists.email', 'subscribers.email')
                ->where(fn ($w) => $w->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId)));

        if ($includes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $q) use ($includes) {
            foreach ($includes as $audience) {
                $q->orWhere(fn (Builder $a) => static::scopeAudience($a, $audience));
            }
        });

        if ($excludes->isNotEmpty()) {
            $query->whereNotIn('subscribers.email', function ($sub) use ($excludes) {
                $exclude = Subscriber::query()->select('email')->where(function (Builder $q) use ($excludes) {
                    foreach ($excludes as $audience) {
                        $q->orWhere(fn (Builder $a) => static::scopeAudience($a, $audience));
                    }
                });
                $sub->fromSub($exclude->toBase(), 'ex')->select('email');
            });
        }

        return $query;
    }

    protected static function scopeAudience(Builder $q, CampaignAudience $audience): void
    {
        $q->where('mail_list_id', $audience->mail_list_id);
        if ($audience->segment) {
            Segment::applyConditions($q, $audience->segment->conditions ?? [], $audience->segment->matching);
        }
    }

    public function estimateRecipients(): int
    {
        return (clone $this->recipientQuery())->distinct()->count('subscribers.email');
    }

    /** True when the sending window allows sending at $at (window times are in workspace timezone). */
    public function inSendingWindow(?Carbon $at = null): bool
    {
        if (empty($this->sending_window)) {
            return true;
        }

        $local = ($at ?? now())->copy()->setTimezone($this->workspace->timezone);
        $day = (int) $local->dayOfWeekIso; // 1 = Mon
        $time = $local->format('H:i');

        foreach ($this->sending_window as $range) {
            if ((int) $range['weekday'] === $day && $time >= $range['start'] && $time < $range['end']) {
                return true;
            }
        }

        return false;
    }

    /** Seconds until the next minute that falls inside the sending window (max 7 days look-ahead). */
    public function secondsUntilWindowOpens(): int
    {
        $probe = now()->startOfMinute();
        for ($i = 0; $i < 7 * 24 * 4; $i++) {
            $probe->addMinutes(15);
            if ($this->inSendingWindow($probe)) {
                return max(60, now()->diffInSeconds($probe));
            }
        }

        return 3600;
    }

    public function stats(): array
    {
        $d = $this->deliveries()->toBase()->selectRaw("
            count(*) as total,
            sum(status in ('sent','bounced','complained')) as sent,
            sum(status = 'failed') as failed,
            sum(status = 'skipped') as skipped,
            sum(status = 'pending') as pending,
            sum(status = 'bounced') as bounced,
            sum(status = 'complained') as complained,
            sum(opened_at is not null) as opened,
            sum(clicked_at is not null) as clicked,
            sum(open_count) as total_opens,
            sum(click_count) as total_clicks
        ")->first();

        $unsubscribed = $this->events()->where('type', 'unsubscribe')->distinct()->count('delivery_id');
        $sent = (int) $d->sent;
        $delivered = max(0, $sent - (int) $d->bounced);
        $rate = fn ($n, $of) => $of > 0 ? round($n / $of * 100, 2) : 0.0;

        return [
            'total' => (int) $d->total,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => (int) $d->failed,
            'skipped' => (int) $d->skipped,
            'pending' => (int) $d->pending,
            'bounced' => (int) $d->bounced,
            'complained' => (int) $d->complained,
            'opened' => (int) $d->opened,
            'clicked' => (int) $d->clicked,
            'total_opens' => (int) $d->total_opens,
            'total_clicks' => (int) $d->total_clicks,
            'unsubscribed' => $unsubscribed,
            'open_rate' => $rate((int) $d->opened, $delivered),
            'click_rate' => $rate((int) $d->clicked, $delivered),
            'ctor' => $rate((int) $d->clicked, (int) $d->opened),
            'bounce_rate' => $rate((int) $d->bounced, $sent),
            'complaint_rate' => $rate((int) $d->complained, $sent),
            'unsubscribe_rate' => $rate($unsubscribed, $delivered),
            'progress' => $d->total > 0 ? (int) round(($d->total - $d->pending) / $d->total * 100) : 0,
        ];
    }

    public function duplicate(): self
    {
        $copy = $this->replicate(['uid', 'status', 'scheduled_at', 'recipients_count', 'sent_count', 'failed_count',
            'skipped_count', 'last_error', 'started_at', 'finished_at', 'winner_variant_id', 'test_ends_at']);
        $copy->name = 'Copy of '.$this->name;
        $copy->status = 'draft';
        $copy->save();

        foreach ($this->audiences as $audience) {
            $copy->audiences()->create($audience->only(['mail_list_id', 'segment_id', 'exclude']));
        }
        foreach ($this->variants as $variant) {
            $copy->variants()->create($variant->only(['label', 'subject', 'from_name', 'preheader', 'html']));
        }

        return $copy;
    }
}

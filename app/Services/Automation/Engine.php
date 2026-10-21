<?php

namespace App\Services\Automation;

use App\Jobs\ProcessAutomationRun;
use App\Jobs\SendAutomationEmail;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Blacklist;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Services\RssFeed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Enrolls subscribers into automations and walks each run through its steps until the run
 * has to wait, reaches the end, or the subscriber may no longer be emailed.
 */
class Engine
{
    /** Guard against accidental loops in a single processing pass. */
    public const MAX_STEPS_PER_PASS = 50;

    public function enroll(Automation $automation, Subscriber $subscriber, string $ref = ''): ?AutomationRun
    {
        if (! $automation->isActive() || $automation->flow()->isEmpty() || $subscriber->mail_list_id !== $automation->mail_list_id) {
            return null;
        }

        if ($automation->allow_reentry && $ref === '') {
            if ($automation->runs()->where('subscriber_id', $subscriber->id)->whereIn('status', ['waiting', 'active'])->exists()) {
                return null; // already on the journey
            }
            $ref = 're:'.now()->format('YmdHis').Str::random(4);
        }

        try {
            $run = $automation->runs()->create([
                'subscriber_id' => $subscriber->id,
                'trigger_ref' => $ref,
                'status' => 'waiting',
                'step_id' => $automation->flow()->start(),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return null; // already enrolled for this trigger occurrence
            }
            throw $e;
        }

        $automation->forceFill(['last_triggered_at' => now()])->saveQuietly();
        ProcessAutomationRun::dispatch($run->id)->afterCommit();

        return $run;
    }

    /** Advances a run as far as it can go right now. */
    public function process(AutomationRun $run): void
    {
        $run->loadMissing('automation', 'subscriber.list');
        $automation = $run->automation;
        $subscriber = $run->subscriber;

        if (! in_array($run->status, ['waiting', 'active'], true) || ! $automation->isActive()) {
            return;
        }
        if (! $subscriber) {
            $this->finish($run, 'exited', 'Contact was deleted');

            return;
        }

        $flow = $automation->flow();
        $run->status = 'active';

        for ($i = 0; $i < self::MAX_STEPS_PER_PASS; $i++) {
            $step = $flow->get($run->step_id);
            if (! $step) {
                $this->finish($run, 'completed', 'Reached the end');

                return;
            }

            if (! $this->mayContinue($automation, $subscriber->refresh())) {
                $this->finish($run, 'exited', 'Contact is no longer subscribed');

                return;
            }

            switch ($step['type']) {
                case 'wait':
                    // wake_at is only ever set while the run sits on a wait step
                    if ($run->wake_at === null) {
                        $until = $this->waitUntil($step, $automation);
                        if ($until->isFuture()) {
                            $run->record($run->step_id, 'Waiting until '.$until->toDateTimeString().' UTC');
                            $run->forceFill(['status' => 'waiting', 'wake_at' => $until])->save();

                            return;
                        }
                    } elseif ($run->wake_at->isFuture()) {
                        $run->forceFill(['status' => 'waiting'])->save();

                        return;
                    }
                    $run->record($run->step_id, 'Wait finished');
                    $run->step_id = $step['next'] ?? null;
                    $run->wake_at = null;
                    break;

                case 'send':
                    $this->send($run, $step);
                    $run->record($run->step_id, 'Email queued');
                    $run->step_id = $step['next'] ?? null;
                    break;

                case 'condition':
                    $result = $this->evaluate($run, $step['rule'] ?? [], $flow);
                    $run->record($run->step_id, 'Condition '.($result ? 'met' : 'not met'));
                    $run->step_id = $result ? ($step['yes'] ?? null) : ($step['no'] ?? null);
                    break;

                case 'action':
                    $this->act($subscriber, $step);
                    $run->record($run->step_id, Flow::ACTIONS[$step['action'] ?? ''] ?? 'Action');
                    $run->step_id = $step['next'] ?? null;
                    if (in_array($step['action'] ?? '', ['move_list', 'unsubscribe'], true)) {
                        $this->finish($run, 'completed', 'Contact left the list');

                        return;
                    }
                    break;
            }

            $run->save();
        }

        // Too many steps in one pass: the tick picks the run up again instead of looping forever
        $run->forceFill(['status' => 'waiting'])->save();
    }

    /**
     * Fires event triggers for a subscriber change.
     *
     * @param  array{tag?: string, field?: string, value?: string}  $context
     */
    public function handleEvent(Subscriber $subscriber, string $event, array $context = []): int
    {
        $count = 0;
        $automations = Automation::where('mail_list_id', $subscriber->mail_list_id)
            ->where('status', 'active')->where('trigger_type', $event)->get();

        foreach ($automations as $automation) {
            $matches = match ($event) {
                'tag_added', 'tag_removed' => strcasecmp((string) $automation->config('tag'), (string) ($context['tag'] ?? '')) === 0,
                'field_updated' => $automation->config('field') === ($context['field'] ?? null)
                    && Str::is(strtolower((string) $automation->config('value', '*')), strtolower((string) ($context['value'] ?? ''))),
                default => true,
            };

            if ($matches && $this->enroll($automation, $subscriber)) {
                $count++;
            }
        }

        return $count;
    }

    /** Called every minute: date-based and recurring triggers, then due runs. */
    public function tick(?Carbon $now = null): void
    {
        $now ??= now();

        Automation::where('status', 'active')
            ->whereIn('trigger_type', ['birthday', 'date_field', 'weekly', 'monthly', 'rss'])
            ->with('workspace', 'list')
            ->each(fn (Automation $automation) => $this->runScheduledTrigger($automation, $now));

        // due waits, plus runs whose first processing job never ran (e.g. worker restart)
        AutomationRun::where('status', 'waiting')
            ->where(fn ($q) => $q->where('wake_at', '<=', $now)
                ->orWhere(fn ($w) => $w->whereNull('wake_at')->where('updated_at', '<', $now->copy()->subMinutes(5))))
            ->orderBy('id')->limit(5000)->pluck('id')
            ->each(fn ($id) => ProcessAutomationRun::dispatch($id));
    }

    public function runScheduledTrigger(Automation $automation, Carbon $now): int
    {
        if ($automation->trigger_type === 'rss') {
            return $this->runRssTrigger($automation);
        }

        $local = $now->copy()->setTimezone($automation->workspace->timezone);
        if ($local->format('H:i') < $automation->config('time', '09:00')) {
            return 0;
        }

        $field = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) $automation->config('field')));
        $query = $automation->list->subscribers()->where('status', 'subscribed');

        switch ($automation->trigger_type) {
            case 'birthday':
                $monthDay = $local->format('m-d');
                $days = [$monthDay];
                if ($monthDay === '02-28' && ! $local->isLeapYear()) {
                    $days[] = '02-29'; // leap-day birthdays are celebrated on the 28th
                }
                $query->where(fn ($q) => collect($days)->each(fn ($d) => $q->orWhere('data->'.$field, 'like', '%-'.$d.'%')));
                $ref = 'y'.$local->year;
                break;

            case 'date_field':
                $target = $local->copy()->subDays((int) $automation->config('offset_days', 0))->toDateString();
                $query->where('data->'.$field, 'like', $target.'%');
                $ref = 'd'.$target;
                break;

            case 'weekly':
                if ($local->dayOfWeekIso !== (int) $automation->config('weekday', 1)) {
                    return 0;
                }
                $ref = 'w'.$local->format('o-W');
                break;

            case 'monthly':
                $day = min((int) $automation->config('monthday', 1), $local->daysInMonth);
                if ($local->day !== $day) {
                    return 0;
                }
                $ref = 'm'.$local->format('Y-m');
                break;

            default:
                return 0;
        }

        $count = 0;
        $query->select('subscribers.*')->chunkById(500, function ($subscribers) use ($automation, $ref, &$count) {
            foreach ($subscribers as $subscriber) {
                if ($this->enroll($automation, $subscriber, $ref)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Enrolls the list when the feed's newest item changes. The first check only remembers the
     * newest item, so activating an automation never mails out old posts.
     */
    public function runRssTrigger(Automation $automation): int
    {
        if (! Cache::add('rss-check:'.$automation->id, true, now()->addMinutes(30))) {
            return 0;
        }

        $latest = app(RssFeed::class)->items((string) $automation->config('url'), 1, fresh: true)[0]['id'] ?? null;
        if ($latest === null || $latest === $automation->config('last_item')) {
            return 0;
        }

        $isBaseline = $automation->config('last_item') === null;
        $automation->forceFill(['trigger_config' => array_merge($automation->trigger_config ?? [], ['last_item' => $latest])])->saveQuietly();
        if ($isBaseline) {
            return 0;
        }

        $count = 0;
        $ref = 'rss:'.substr(md5($latest), 0, 16);
        $automation->list->subscribers()->where('status', 'subscribed')->select('subscribers.*')
            ->chunkById(500, function ($subscribers) use ($automation, $ref, &$count) {
                foreach ($subscribers as $subscriber) {
                    if ($this->enroll($automation, $subscriber, $ref)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function evaluate(AutomationRun $run, array $rule, Flow $flow): bool
    {
        $subscriber = $run->subscriber;

        return match ($rule['kind'] ?? '') {
            'opened', 'clicked' => $this->engaged($run, $rule, $flow),
            'tag' => in_array(strtolower((string) ($rule['tag'] ?? '')), array_map('strtolower', $subscriber->tags ?? []), true),
            'field' => Segment::applyConditions(
                Subscriber::whereKey($subscriber->id),
                [['field' => $rule['field'] ?? 'EMAIL', 'operator' => $rule['operator'] ?? 'equal', 'value' => $rule['value'] ?? '']]
            )->exists(),
            'subscribed' => $subscriber->status === 'subscribed',
            default => false,
        };
    }

    protected function engaged(AutomationRun $run, array $rule, Flow $flow): bool
    {
        $campaignIds = collect($flow->sendSteps())
            ->filter(fn ($s, $id) => empty($rule['step']) || $rule['step'] === $id)
            ->pluck('campaign_id')->filter()->all();

        return Delivery::whereIn('campaign_id', $campaignIds)
            ->where('automation_run_id', $run->id)
            ->whereNotNull($rule['kind'] === 'clicked' ? 'clicked_at' : 'opened_at')
            ->exists();
    }

    public function waitUntil(array $step, Automation $automation): Carbon
    {
        $amount = max(0, (int) ($step['amount'] ?? 1));

        return match ($step['unit'] ?? 'days') {
            'minutes' => now()->addMinutes($amount),
            'hours' => now()->addHours($amount),
            'weeks' => now()->addWeeks($amount),
            default => now()->addDays($amount),
        };
    }

    protected function send(AutomationRun $run, array $step): void
    {
        $campaign = $run->automation->emails()->find($step['campaign_id'] ?? 0);
        $subscriber = $run->subscriber;
        if (! $campaign || Blacklist::isBlocked($subscriber->email, $campaign->workspace_id)) {
            return;
        }

        $delivery = Delivery::firstOrCreate(
            ['campaign_id' => $campaign->id, 'email' => $subscriber->email, 'automation_run_id' => $run->id],
            ['subscriber_id' => $subscriber->id, 'workspace_id' => $campaign->workspace_id, 'kind' => 'automation', 'status' => 'pending']
        );

        if ($delivery->wasRecentlyCreated) {
            SendAutomationEmail::dispatch($delivery->id)->afterCommit();
        }
    }

    protected function act(Subscriber $subscriber, array $step): void
    {
        $workspaceLists = fn () => MailList::where('workspace_id', $subscriber->list->workspace_id);

        switch ($step['action'] ?? '') {
            case 'add_tag':
                $subscriber->addTags([(string) $step['tag']]);
                $subscriber->save();
                break;
            case 'remove_tag':
                $subscriber->removeTags([(string) $step['tag']]);
                $subscriber->save();
                break;
            case 'update_field':
                $tag = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) $step['field']));
                $subscriber->data = array_merge($subscriber->data ?? [], [$tag => (string) ($step['value'] ?? '')]);
                $subscriber->save();
                break;
            case 'copy_list':
            case 'move_list':
                $target = $workspaceLists()->find($step['list_id'] ?? 0);
                if ($target && $target->id !== $subscriber->mail_list_id) {
                    $target->subscribers()->firstOrCreate(['email' => $subscriber->email], [
                        'status' => $subscriber->status, 'data' => $subscriber->data, 'tags' => $subscriber->tags,
                        'source' => 'automation', 'confirmed_at' => $subscriber->confirmed_at,
                    ]);
                    if ($step['action'] === 'move_list') {
                        $subscriber->unsubscribe();
                    }
                }
                break;
            case 'unsubscribe':
                $subscriber->unsubscribe();
                break;
        }
    }

    /** Goodbye flows may email people who just unsubscribed; everything else needs a live subscription. */
    protected function mayContinue(Automation $automation, Subscriber $subscriber): bool
    {
        if ($subscriber->status === 'blacklisted') {
            return false;
        }

        return $automation->trigger_type === 'unsubscribed' || $subscriber->status === 'subscribed';
    }

    protected function finish(AutomationRun $run, string $status, string $note): void
    {
        $run->record((string) $run->step_id, $note);
        $run->forceFill(['status' => $status, 'wake_at' => null, 'completed_at' => now()])->save();
    }
}

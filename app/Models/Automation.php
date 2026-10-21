<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use App\Services\Automation\Flow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use BelongsToWorkspace, HasFactory, HasUid;

    /**
     * Trigger catalogue. 'config' lists the settings each trigger needs; 'enrolls' says how
     * contacts enter: on an event, on a matching date (checked daily), or on a schedule.
     *
     * @var array<string, array{label: string, description: string, enrolls: string, config: string[]}>
     */
    public const TRIGGERS = [
        'subscribed' => ['label' => 'Welcome new subscribers', 'description' => 'Starts when someone joins the list (after confirming, with double opt-in).', 'enrolls' => 'event', 'config' => []],
        'unsubscribed' => ['label' => 'Say goodbye', 'description' => 'Starts when someone unsubscribes. Only transactional-style follow-ups are allowed.', 'enrolls' => 'event', 'config' => []],
        'birthday' => ['label' => 'Birthday / anniversary', 'description' => 'Every year on the month and day stored in a date field.', 'enrolls' => 'date', 'config' => ['field', 'time']],
        'date_field' => ['label' => 'Specific date', 'description' => 'Once, a number of days before or after a date field (appointments, renewals, trials).', 'enrolls' => 'date', 'config' => ['field', 'offset_days', 'time']],
        'tag_added' => ['label' => 'Tag added', 'description' => 'Starts when a tag is added to a contact.', 'enrolls' => 'event', 'config' => ['tag']],
        'tag_removed' => ['label' => 'Tag removed', 'description' => 'Starts when a tag is removed from a contact.', 'enrolls' => 'event', 'config' => ['tag']],
        'field_updated' => ['label' => 'Contact field changes', 'description' => 'Starts when a field changes to a matching value (* and ? wildcards allowed).', 'enrolls' => 'event', 'config' => ['field', 'value']],
        'weekly' => ['label' => 'Weekly recurring', 'description' => 'Every week on the chosen day and time, for every subscribed contact.', 'enrolls' => 'schedule', 'config' => ['weekday', 'time']],
        'monthly' => ['label' => 'Monthly recurring', 'description' => 'Every month on the chosen day and time, for every subscribed contact.', 'enrolls' => 'schedule', 'config' => ['monthday', 'time']],
    ];

    protected $fillable = ['workspace_id', 'mail_list_id', 'name', 'trigger_type', 'trigger_config', 'status', 'flow', 'allow_reentry'];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'flow' => 'array',
            'allow_reentry' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Automation $automation) {
            $automation->flow ??= Flow::empty();
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /** Hidden campaigns that hold the content of each "send email" step. */
    public function emails(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function flow(): Flow
    {
        return new Flow($this->flow ?? Flow::empty());
    }

    public function setFlow(Flow $flow): void
    {
        $this->flow = $flow->toArray();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function triggerLabel(): string
    {
        return self::TRIGGERS[$this->trigger_type]['label'] ?? $this->trigger_type;
    }

    public function config(string $key, $default = null)
    {
        return $this->trigger_config[$key] ?? $default;
    }

    /** Human description of the trigger with its settings, e.g. "Tag added: vip". */
    public function triggerSummary(): string
    {
        $c = $this->trigger_config ?? [];
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        return match ($this->trigger_type) {
            'birthday' => 'Every year on '.($c['field'] ?? '?').' at '.($c['time'] ?? '09:00'),
            'date_field' => (($c['offset_days'] ?? 0) == 0 ? 'On' : abs((int) $c['offset_days']).' days '.((int) $c['offset_days'] < 0 ? 'before' : 'after'))
                .' '.($c['field'] ?? '?').' at '.($c['time'] ?? '09:00'),
            'tag_added' => 'Tag “'.($c['tag'] ?? '').'” added',
            'tag_removed' => 'Tag “'.($c['tag'] ?? '').'” removed',
            'field_updated' => ($c['field'] ?? '?').' changes to “'.($c['value'] ?? '*').'”',
            'weekly' => 'Every '.($days[$c['weekday'] ?? 1] ?? '').' at '.($c['time'] ?? '09:00'),
            'monthly' => 'Day '.($c['monthday'] ?? 1).' of every month at '.($c['time'] ?? '09:00'),
            default => $this->triggerLabel(),
        };
    }

    /** @return array{total: int, active: int, completed: int, exited: int} */
    public function runCounts(): array
    {
        $counts = $this->runs()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) (($counts['waiting'] ?? 0) + ($counts['active'] ?? 0)),
            'completed' => (int) ($counts['completed'] ?? 0),
            'exited' => (int) (($counts['exited'] ?? 0) + ($counts['failed'] ?? 0)),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    public const STATUSES = ['waiting', 'active', 'completed', 'exited', 'failed'];

    protected $fillable = ['automation_id', 'subscriber_id', 'trigger_ref', 'status', 'step_id', 'wake_at'];

    protected function casts(): array
    {
        return ['history' => 'array', 'wake_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function record(string $stepId, string $note): void
    {
        $history = $this->history ?? [];
        $history[] = ['step' => $stepId, 'note' => $note, 'at' => now()->toIso8601String()];
        // keep the journey log bounded for long recurring automations
        $this->history = array_slice($history, -100);
    }
}

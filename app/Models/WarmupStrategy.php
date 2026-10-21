<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarmupStrategy extends Model
{
    use HasUid;

    public const CURVES = ['linear' => 'Linear — equal daily increase', 'exponential' => 'Exponential — slow start, faster growth'];

    /** Built-in presets seeded for every installation. */
    public const PRESETS = [
        ['name' => 'Conservative — 30 days', 'curve' => 'linear', 'initial_daily' => 50, 'target_daily' => 10000, 'days' => 30],
        ['name' => 'Standard — 21 days', 'curve' => 'exponential', 'initial_daily' => 100, 'target_daily' => 50000, 'days' => 21],
        ['name' => 'Fast — 14 days', 'curve' => 'exponential', 'initial_daily' => 200, 'target_daily' => 50000, 'days' => 14],
    ];

    protected $fillable = ['workspace_id', 'name', 'curve', 'initial_daily', 'target_daily', 'days'];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'uid', $value)->availableTo(Workspace::current())->firstOrFail();
    }

    public function scopeAvailableTo($query, ?Workspace $workspace)
    {
        return $query->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspace?->id));
    }

    public function servers(): HasMany
    {
        return $this->hasMany(SendingServer::class);
    }

    public function isPreset(): bool
    {
        return $this->workspace_id === null;
    }

    /** Allowed sends on warmup day $day (1-based). After the last day the target applies. */
    public function quotaForDay(int $day): int
    {
        $day = max(1, $day);
        if ($day >= $this->days || $this->days <= 1) {
            return $this->target_daily;
        }

        $progress = ($day - 1) / ($this->days - 1);

        return (int) round($this->curve === 'exponential'
            ? $this->initial_daily * (($this->target_daily / max(1, $this->initial_daily)) ** $progress)
            : $this->initial_daily + ($this->target_daily - $this->initial_daily) * $progress);
    }

    /** @return array<int, int> day => quota */
    public function schedule(): array
    {
        return collect(range(1, $this->days))->mapWithKeys(fn ($d) => [$d => $this->quotaForDay($d)])->all();
    }
}

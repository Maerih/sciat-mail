<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Route-model binding only resolves records of the active workspace, so a uid
 * from another client's workspace 404s instead of leaking data.
 */
trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $query, Workspace|int|null $workspace = null): Builder
    {
        $id = $workspace instanceof Workspace ? $workspace->id : ($workspace ?? Workspace::current()?->id);

        return $query->where($this->getTable().'.workspace_id', $id);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->forWorkspace()
            ->firstOrFail();
    }
}

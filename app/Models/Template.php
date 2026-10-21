<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasUid;

    protected $fillable = ['workspace_id', 'name', 'category', 'html', 'builder_json'];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'uid', $value)->availableTo(Workspace::current())->firstOrFail();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isSystem(): bool
    {
        return $this->workspace_id === null;
    }

    public function scopeAvailableTo($query, Workspace $workspace)
    {
        return $query->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id));
    }
}

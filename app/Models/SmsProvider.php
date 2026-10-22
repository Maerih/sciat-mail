<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use App\Sms\SmsManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** An SMS gateway account. workspace_id null = shared with every client (admin managed). */
class SmsProvider extends Model
{
    use HasUid;

    protected $fillable = ['workspace_id', 'name', 'type', 'config', 'sender_id', 'status', 'per_second'];

    protected $hidden = ['config', 'secret'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_default' => 'boolean', 'last_tested_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (SmsProvider $provider) => $provider->secret ??= Str::random(32));
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::availableTo(Workspace::current())->where($field ?? 'uid', $value)->firstOrFail();
    }

    public function scopeAvailableTo(Builder $query, ?Workspace $workspace): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspace?->id));
    }

    public static function defaultFor(Workspace $workspace): ?self
    {
        return static::availableTo($workspace)->where('status', 'active')
            ->orderByRaw('workspace_id is null')->orderByDesc('is_default')->oldest('id')->first();
    }

    public function isShared(): bool
    {
        return $this->workspace_id === null;
    }

    public function typeLabel(): string
    {
        return SmsManager::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function configValue(string $key, $default = null)
    {
        return ($this->config ?? [])[$key] ?? $default;
    }

    public function webhookUrl(string $kind): string
    {
        return route('webhooks.sms', [$this->uid, $this->secret, $kind]);
    }
}

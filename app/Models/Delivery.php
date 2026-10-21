<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Delivery extends Model
{
    protected $fillable = [
        'uid', 'campaign_id', 'subscriber_id', 'automation_run_id', 'workspace_id', 'email', 'kind', 'status', 'sending_server_id',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'opened_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Delivery $d) => $d->uid ??= static::newUid());
    }

    public static function newUid(): string
    {
        return strtolower(Str::random(24));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sendingServer(): BelongsTo
    {
        return $this->belongsTo(SendingServer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }

    public function logEvent(string $type, array $attributes = []): DeliveryEvent
    {
        return $this->events()->create($attributes + [
            'type' => $type,
            'campaign_id' => $this->campaign_id,
            'subscriber_id' => $this->subscriber_id,
        ]);
    }
}

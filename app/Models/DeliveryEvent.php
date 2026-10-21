<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['open', 'click', 'bounce', 'complaint', 'unsubscribe'];

    protected $fillable = ['type', 'subtype', 'url', 'ip', 'user_agent', 'detail', 'campaign_id', 'subscriber_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}

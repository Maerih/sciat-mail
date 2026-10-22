<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['subscriber_id', 'visitor', 'path', 'referrer'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}

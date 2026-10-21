<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use BelongsToWorkspace, HasUid;

    public const EVENTS = [
        'subscriber.subscribed' => 'Contact subscribed',
        'subscriber.unsubscribed' => 'Contact unsubscribed',
        'email.opened' => 'Email opened',
        'email.clicked' => 'Link clicked',
        'email.bounced' => 'Email bounced',
        'email.complained' => 'Spam complaint',
        'campaign.finished' => 'Campaign finished sending',
    ];

    /** Consecutive failed deliveries after which the endpoint is switched off. */
    public const DISABLE_AFTER = 50;

    protected $fillable = ['workspace_id', 'url', 'events', 'active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Webhook $w) => $w->secret ??= 'whsec_'.Str::random(32));
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    public function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $this->secret);
    }
}

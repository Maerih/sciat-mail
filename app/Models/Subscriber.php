<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasUid;

    public const STATUSES = ['subscribed', 'unconfirmed', 'unsubscribed', 'blacklisted'];

    protected $fillable = [
        'mail_list_id', 'email', 'status', 'data', 'tags', 'source', 'ip', 'confirmed_at', 'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'tags' => 'array',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_open_at' => 'datetime',
            'last_click_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subscriber $s) {
            $s->email = strtolower(trim($s->email));
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }

    public function field(string $tag): ?string
    {
        if ($tag === 'EMAIL') {
            return $this->email;
        }

        return isset($this->data[$tag]) ? (string) $this->data[$tag] : null;
    }

    public function fullName(): string
    {
        return trim(($this->field('FIRST_NAME') ?? '').' '.($this->field('LAST_NAME') ?? ''));
    }

    public function addTags(array $tags): void
    {
        $this->tags = array_values(array_unique(array_merge($this->tags ?? [], array_map('trim', $tags))));
    }

    public function removeTags(array $tags): void
    {
        $this->tags = array_values(array_diff($this->tags ?? [], $tags));
    }

    public function unsubscribe(): void
    {
        $this->forceFill(['status' => 'unsubscribed', 'unsubscribed_at' => now()])->save();
    }

    public function isSendable(): bool
    {
        return $this->status === 'subscribed';
    }
}

<?php

namespace App\Models;

use App\Jobs\TriggerAutomations;
use App\Models\Concerns\HasUid;
use App\Services\Webhooks;
use App\Sms\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasFactory, HasUid;

    public const STATUSES = ['subscribed', 'unconfirmed', 'unsubscribed', 'blacklisted'];

    protected $fillable = [
        'mail_list_id', 'email', 'status', 'data', 'tags', 'source', 'ip', 'confirmed_at', 'unsubscribed_at', 'sms_opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'tags' => 'array',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'sms_opted_out_at' => 'datetime',
            'last_open_at' => 'datetime',
            'last_click_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subscriber $s) {
            $s->email = strtolower(trim($s->email));
            // Keep the normalised SMS number in step with the list's phone field
            if ($s->isDirty('data') && $s->mail_list_id && ($tag = $s->list?->phoneTag())) {
                $s->phone = PhoneNumber::normalize($s->data[$tag] ?? null, $s->list->workspace?->sms_country);
            }
        });

        static::created(function (Subscriber $s) {
            if ($s->status === 'subscribed' && $s->source !== 'import') {
                static::fireAutomation($s, 'subscribed');
                Webhooks::dispatch($s->list->workspace_id, 'subscriber.subscribed', $s->webhookPayload());
            }
        });

        static::updated(function (Subscriber $s) {
            if ($s->wasChanged('status') && in_array($s->status, ['subscribed', 'unsubscribed'], true)) {
                static::fireAutomation($s, $s->status);
                Webhooks::dispatch($s->list->workspace_id, 'subscriber.'.$s->status, $s->webhookPayload());
            }
            if ($s->wasChanged('tags')) {
                $before = array_map('strtolower', json_decode((string) $s->getRawOriginal('tags'), true) ?? []);
                $after = array_map('strtolower', $s->tags ?? []);
                foreach (array_diff($after, $before) as $tag) {
                    static::fireAutomation($s, 'tag_added', ['tag' => $tag]);
                }
                foreach (array_diff($before, $after) as $tag) {
                    static::fireAutomation($s, 'tag_removed', ['tag' => $tag]);
                }
            }
            if ($s->wasChanged('data')) {
                $before = json_decode((string) $s->getRawOriginal('data'), true) ?? [];
                foreach ($s->data ?? [] as $field => $value) {
                    if (($before[$field] ?? null) !== $value) {
                        static::fireAutomation($s, 'field_updated', ['field' => $field, 'value' => (string) $value]);
                    }
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public function webhookPayload(): array
    {
        return [
            'uid' => $this->uid,
            'email' => $this->email,
            'status' => $this->status,
            'list' => ['uid' => $this->list->uid, 'name' => $this->list->name],
            'fields' => $this->data ?? (object) [],
            'tags' => $this->tags ?? [],
        ];
    }

    /** Queues automation triggers only when the list has an active automation listening for them. */
    protected static function fireAutomation(Subscriber $subscriber, string $event, array $context = []): void
    {
        $listening = Automation::where('mail_list_id', $subscriber->mail_list_id)
            ->where('status', 'active')->where('trigger_type', $event)->exists();

        if ($listening) {
            TriggerAutomations::dispatch($subscriber->id, $event, $context)->afterCommit();
        }
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

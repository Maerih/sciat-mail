<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxThread extends Model
{
    use HasUid;

    protected $fillable = ['inbox_mailbox_id', 'subject', 'contact_email', 'contact_name', 'snippet', 'message_count', 'unread', 'starred', 'status', 'assigned_user_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['starred' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(InboxMailbox::class, 'inbox_mailbox_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class)->orderBy('sent_at')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::whereIn('inbox_mailbox_id', InboxMailbox::visibleTo(request()->user())->select('id'))->where($field ?? 'uid', $value)->firstOrFail();
    }

    /** Recomputes counters and the preview after messages change. */
    public function refreshSummary(): void
    {
        $last = $this->messages()->reorder()->latest('sent_at')->latest('id')->first();
        $this->forceFill([
            'message_count' => $this->messages()->count(),
            'unread' => $this->messages()->where('direction', 'in')->where('is_read', false)->count(),
            'last_message_at' => $last?->sent_at,
            'snippet' => $last ? mb_substr(trim(preg_replace('/\s+/', ' ', $last->plainText())), 0, 300) : null,
        ])->save();
    }

    public static function normalizeSubject(?string $subject): string
    {
        return strtolower(trim(preg_replace('/^((re|fw|fwd|aw|sv|tr)\s*(\[\d+\])?:\s*)+/i', '', (string) $subject)));
    }
}

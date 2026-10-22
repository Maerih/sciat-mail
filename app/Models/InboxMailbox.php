<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company mailbox (info@, sales@…) read over IMAP and answered over SMTP from the team inbox. */
class InboxMailbox extends Model
{
    use HasUid;

    protected $fillable = ['name', 'email', 'from_name', 'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption', 'username', 'password', 'signature', 'color', 'status'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'last_synced_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inbox_mailbox_user');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(InboxThread::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class);
    }

    /** Admins see every mailbox; others see unrestricted mailboxes and those they are a member of. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where(fn ($q) => $q->whereDoesntHave('members')->orWhereHas('members', fn ($m) => $m->whereKey($user->id)));
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::visibleTo(request()->user())->where($field ?? 'uid', $value)->firstOrFail();
    }
}

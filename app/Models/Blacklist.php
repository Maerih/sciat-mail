<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $fillable = ['workspace_id', 'email', 'reason', 'note'];

    public const REASONS = ['manual' => 'Manual', 'hard_bounce' => 'Hard bounce', 'complaint' => 'Spam complaint', 'import' => 'Imported'];

    /** Suppress an address and flip its subscriptions in the affected workspace(s). */
    public static function add(string $email, ?int $workspaceId, string $reason = 'manual', ?string $note = null): self
    {
        $email = strtolower(trim($email));
        $entry = static::firstOrCreate(['workspace_id' => $workspaceId, 'email' => $email], ['reason' => $reason, 'note' => $note]);

        Subscriber::where('email', $email)
            ->when($workspaceId, fn ($q) => $q->whereIn('mail_list_id', MailList::where('workspace_id', $workspaceId)->select('id')))
            ->update(['status' => 'blacklisted']);

        return $entry;
    }

    public static function isBlocked(string $email, int $workspaceId): bool
    {
        return static::where('email', strtolower($email))
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->exists();
    }
}

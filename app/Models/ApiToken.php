<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Workspace-scoped API token. Only a SHA-256 hash is stored; the plain token is shown once.
 */
class ApiToken extends Model
{
    protected $fillable = ['workspace_id', 'user_id', 'name'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return array{0: ApiToken, 1: string} the token and its plain-text value */
    public static function issue(Workspace $workspace, string $name, ?User $user = null): array
    {
        $plain = 'sm_'.Str::random(40);
        $token = new static(['workspace_id' => $workspace->id, 'user_id' => $user?->id, 'name' => $name]);
        $token->token_hash = hash('sha256', $plain);
        $token->prefix = substr($plain, 0, 10);
        $token->save();

        return [$token, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::where('token_hash', hash('sha256', $plain))->first();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

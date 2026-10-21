<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;

class VerificationServer extends Model
{
    use BelongsToWorkspace, HasUid;

    /** @var array<string, array{label: string, description: string, needs_key: bool}> */
    public const PROVIDERS = [
        'builtin' => ['label' => 'Built-in check (free)', 'description' => 'Syntax, MX records, disposable domains and role addresses. No mailbox probing.', 'needs_key' => false],
        'emailable' => ['label' => 'Emailable', 'description' => 'Real-time mailbox verification by Emailable.', 'needs_key' => true],
        'kickbox' => ['label' => 'Kickbox', 'description' => 'Deliverability and risk scoring by Kickbox.', 'needs_key' => true],
        'zerobounce' => ['label' => 'ZeroBounce', 'description' => 'Verification with spam-trap and abuse detection by ZeroBounce.', 'needs_key' => true],
    ];

    public const STATUSES = ['deliverable', 'risky', 'undeliverable', 'unknown', 'unverified'];

    protected $fillable = ['workspace_id', 'name', 'provider', 'api_key', 'status', 'is_default'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'is_default' => 'boolean'];
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider]['label'] ?? $this->provider;
    }

    /** The workspace's chosen verifier, falling back to the free built-in check. */
    public static function activeFor(Workspace $workspace): self
    {
        return static::where('workspace_id', $workspace->id)->where('status', 'active')->orderByDesc('is_default')->first()
            ?? new static(['workspace_id' => $workspace->id, 'name' => 'Built-in check', 'provider' => 'builtin']);
    }
}

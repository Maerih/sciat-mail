<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A seed inbox you own (Gmail, Outlook, your own server) that receives and rescues warmup emails. */
class WarmupMailbox extends Model
{
    use BelongsToWorkspace, HasUid;

    protected $fillable = ['email', 'host', 'port', 'encryption', 'username', 'password', 'status'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'last_checked_at' => 'datetime'];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(WarmupEmail::class);
    }
}

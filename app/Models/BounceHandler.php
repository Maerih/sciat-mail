<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;

class BounceHandler extends Model
{
    use BelongsToWorkspace, HasUid;

    protected $fillable = ['workspace_id', 'name', 'host', 'port', 'encryption', 'username', 'password', 'folder', 'delete_processed', 'status'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'delete_processed' => 'boolean', 'last_checked_at' => 'datetime'];
    }
}

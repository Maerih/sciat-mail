<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;

/** Reusable email signature. The default one fills the {{ SIGNATURE }} merge tag. */
class Signature extends Model
{
    use BelongsToWorkspace, HasUid;

    protected $fillable = ['name', 'html'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function defaultFor(int $workspaceId): ?self
    {
        return static::where('workspace_id', $workspaceId)->orderByDesc('is_default')->oldest('id')->first();
    }

    public function makeDefault(): void
    {
        static::where('workspace_id', $this->workspace_id)->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }
}

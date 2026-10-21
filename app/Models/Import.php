<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasUid;

    protected $fillable = ['mail_list_id', 'user_id', 'path', 'original_name', 'mapping', 'status', 'total'];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'errors' => 'array', 'finished_at' => 'datetime'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function progress(): int
    {
        return $this->total > 0 ? (int) min(100, round($this->processed / $this->total * 100)) : ($this->status === 'done' ? 100 : 0);
    }
}

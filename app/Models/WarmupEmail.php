<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmupEmail extends Model
{
    protected $fillable = ['workspace_id', 'sending_server_id', 'warmup_mailbox_id', 'token', 'subject', 'placement', 'rescued', 'sent_at', 'checked_at'];

    protected function casts(): array
    {
        return ['rescued' => 'boolean', 'sent_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(SendingServer::class, 'sending_server_id');
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(WarmupMailbox::class, 'warmup_mailbox_id');
    }
}

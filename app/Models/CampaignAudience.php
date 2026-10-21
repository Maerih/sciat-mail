<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignAudience extends Model
{
    public $timestamps = false;

    protected $fillable = ['mail_list_id', 'segment_id', 'exclude'];

    protected function casts(): array
    {
        return ['exclude' => 'boolean'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }
}

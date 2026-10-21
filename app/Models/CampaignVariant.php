<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignVariant extends Model
{
    protected $fillable = ['label', 'subject', 'from_name', 'preheader', 'html'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** What this variant changes compared with the campaign, for display. */
    public function summary(): string
    {
        return collect([
            'subject' => $this->subject ? '“'.$this->subject.'”' : null,
            'from' => $this->from_name ? 'from '.$this->from_name : null,
            'content' => $this->html ? 'own content' : null,
        ])->filter()->implode(' · ') ?: 'Same as default';
    }
}

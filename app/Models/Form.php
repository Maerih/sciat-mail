<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Form extends Model
{
    use HasUid;

    protected $fillable = [
        'name', 'title', 'button_text', 'redirect_url', 'success_message', 'only_required_fields',
        'consent_enabled', 'consent_text', 'status',
    ];

    protected function casts(): array
    {
        return ['only_required_fields' => 'boolean', 'consent_enabled' => 'boolean'];
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'uid', $value)
            ->whereHas('list', fn ($q) => $q->where('workspace_id', Workspace::current()?->id))
            ->firstOrFail();
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function visibleFields()
    {
        // getAttribute(): inside a Model subclass `$f->visible` would hit Eloquent's protected $visible property
        return $this->list->fields
            ->filter(fn (ListField $f) => $f->getAttribute('visible') && (! $this->only_required_fields || $f->required));
    }
}

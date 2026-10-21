<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Form extends Model
{
    use HasUid;

    /** @var array<string, int|string> */
    public const POPUP_DEFAULTS = [
        'trigger' => 'delay', 'delay' => 5, 'scroll' => 50, 'position' => 'center',
        'frequency_days' => 7, 'color' => '#4f46e5', 'description' => '',
    ];

    public const POSITIONS = ['center' => 'Centre modal', 'slide-right' => 'Slide-in (bottom right)', 'bottom-bar' => 'Bottom bar', 'top-bar' => 'Top bar'];

    public const TRIGGERS = ['delay' => 'After a delay', 'scroll' => 'After scrolling', 'exit' => 'On exit intent (desktop)', 'immediate' => 'Immediately'];

    protected $fillable = [
        'display', 'popup_settings', 'name', 'title', 'button_text', 'redirect_url', 'success_message', 'only_required_fields',
        'consent_enabled', 'consent_text', 'status',
    ];

    protected function casts(): array
    {
        return ['only_required_fields' => 'boolean', 'consent_enabled' => 'boolean', 'popup_settings' => 'array'];
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'uid', $value)
            ->whereHas('list', fn ($q) => $q->where('workspace_id', Workspace::current()?->id))
            ->firstOrFail();
    }

    public function isPopup(): bool
    {
        return $this->display === 'popup';
    }

    public function popup(string $key): int|string
    {
        return ($this->popup_settings ?? [])[$key] ?? self::POPUP_DEFAULTS[$key];
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

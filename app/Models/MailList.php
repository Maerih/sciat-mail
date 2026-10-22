<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailList extends Model
{
    use BelongsToWorkspace, HasFactory, HasUid;

    protected $fillable = [
        'workspace_id', 'name', 'from_name', 'from_email', 'reply_to', 'company', 'address',
        'default_sending_server_id', 'double_optin', 'send_welcome_email', 'unsubscribe_notification',
        'notification_email', 'welcome_subject', 'welcome_html', 'phone_field',
    ];

    protected function casts(): array
    {
        return [
            'double_optin' => 'boolean',
            'send_welcome_email' => 'boolean',
            'unsubscribe_notification' => 'boolean',
        ];
    }

    public const DEFAULT_FIELDS = [
        ['label' => 'First Name', 'tag' => 'FIRST_NAME'],
        ['label' => 'Last Name', 'tag' => 'LAST_NAME'],
    ];

    protected static function booted(): void
    {
        static::created(function (MailList $list) {
            foreach (self::DEFAULT_FIELDS as $i => $field) {
                $list->fields()->create($field + ['type' => 'text', 'sort' => $i + 1]);
            }
        });
    }

    public const PHONE_TAGS = ['PHONE', 'MOBILE', 'PHONE_NUMBER', 'MOBILE_NUMBER', 'CELL', 'TELEPHONE', 'MSISDN'];

    /** The field holding SMS numbers: the chosen one, else a phone-type field, else a field named like PHONE. */
    public function phoneTag(): ?string
    {
        if ($this->phone_field) {
            return $this->phone_field;
        }
        $fields = $this->relationLoaded('fields') ? $this->fields : $this->fields()->get();

        return $fields->firstWhere('type', 'phone')?->tag
            ?? $fields->first(fn ($field) => in_array(strtoupper($field->tag), self::PHONE_TAGS, true))?->tag;
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ListField::class)->orderBy('sort');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function defaultSendingServer(): BelongsTo
    {
        return $this->belongsTo(SendingServer::class, 'default_sending_server_id');
    }

    public function statusCounts(): array
    {
        $counts = $this->subscribers()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return collect(Subscriber::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();
    }

    /** Postal footer required by CAN-SPAM; falls back to the workspace details. */
    public function postalAddress(): string
    {
        return collect([$this->company ?: $this->workspace->company, $this->address ?: $this->workspace->address])
            ->filter()->implode(', ');
    }
}

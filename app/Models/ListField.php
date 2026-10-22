<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListField extends Model
{
    protected $fillable = ['label', 'tag', 'type', 'options', 'default_value', 'required', 'visible', 'sort'];

    public const TYPES = ['text' => 'Text', 'number' => 'Number', 'date' => 'Date', 'dropdown' => 'Dropdown', 'checkbox' => 'Checkbox', 'phone' => 'Phone number'];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'visible' => 'boolean'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function mergeTag(): string
    {
        return '{{ SUBSCRIBER_'.$this->tag.' }}';
    }

    public function validationRules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        return array_merge($rules, match ($this->type) {
            'number' => ['numeric'],
            'date' => ['date'],
            'dropdown' => ['in:'.implode(',', $this->options ?? [])],
            'checkbox' => ['boolean'],
            'phone' => ['string', 'max:32', 'regex:/^[+\d\s().-]{7,32}$/'],
            default => ['string', 'max:1000'],
        });
    }
}

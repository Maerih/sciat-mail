<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    use HasUid;

    protected $fillable = ['name', 'matching', 'conditions'];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }

    /** Operators per value kind; the UI and the query builder both read from here. */
    public const OPERATORS = [
        'text' => [
            'equal' => 'is', 'not_equal' => 'is not', 'contains' => 'contains', 'not_contains' => 'does not contain',
            'starts' => 'starts with', 'ends' => 'ends with', 'blank' => 'is blank', 'not_blank' => 'is not blank',
            'greater' => 'is greater than', 'less' => 'is less than',
        ],
        'status' => ['equal' => 'is', 'not_equal' => 'is not'],
        'verification' => ['equal' => 'is', 'not_equal' => 'is not'],
        'tag' => ['has' => 'has tag', 'not_has' => 'does not have tag'],
        'date' => ['before' => 'is before', 'after' => 'is after'],
        'activity' => ['within' => 'in the last N days', 'not_within' => 'not in the last N days'],
        'never' => ['is' => 'is true'],
    ];

    /** Non-custom-field condition targets. */
    public const SPECIAL_FIELDS = [
        'status' => ['label' => 'Status', 'kind' => 'status'],
        'tags' => ['label' => 'Tags', 'kind' => 'tag'],
        'verification_status' => ['label' => 'Verification result', 'kind' => 'verification'],
        'created_at' => ['label' => 'Date added', 'kind' => 'date'],
        'last_open_at' => ['label' => 'Last opened', 'kind' => 'activity'],
        'last_click_at' => ['label' => 'Last clicked', 'kind' => 'activity'],
        'never_opened' => ['label' => 'Never opened any email', 'kind' => 'never'],
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }

    public function subscribersQuery(): Builder
    {
        return static::applyConditions(
            Subscriber::query()->where('mail_list_id', $this->mail_list_id),
            $this->conditions ?? [],
            $this->matching
        );
    }

    public static function applyConditions(Builder $query, array $conditions, string $matching = 'all'): Builder
    {
        if (empty($conditions)) {
            return $query;
        }

        $boolean = $matching === 'any' ? 'or' : 'and';

        return $query->where(function (Builder $q) use ($conditions, $boolean) {
            foreach ($conditions as $condition) {
                $q->where(fn (Builder $c) => static::applyCondition($c, $condition), null, null, $boolean);
            }
        });
    }

    protected static function applyCondition(Builder $q, array $c): void
    {
        $field = $c['field'] ?? '';
        $op = $c['operator'] ?? 'equal';
        $value = $c['value'] ?? null;

        switch ($field) {
            case 'status':
                $q->where('status', $op === 'not_equal' ? '!=' : '=', $value);

                return;
            case 'verification_status':
                $q->where('verification_status', $op === 'not_equal' ? '!=' : '=', $value);

                return;
            case 'tags':
                $op === 'not_has'
                    ? $q->where(fn ($t) => $t->whereNull('tags')->orWhereJsonDoesntContain('tags', $value))
                    : $q->whereJsonContains('tags', $value);

                return;
            case 'created_at':
                $q->whereDate('created_at', $op === 'before' ? '<' : '>', $value);

                return;
            case 'last_open_at':
            case 'last_click_at':
                $since = now()->subDays(max(0, (int) $value));
                $op === 'not_within'
                    ? $q->where(fn ($t) => $t->whereNull($field)->orWhere($field, '<', $since))
                    : $q->where($field, '>=', $since);

                return;
            case 'never_opened':
                $q->whereNull('last_open_at');

                return;
        }

        $column = $field === 'EMAIL' ? 'email' : 'data->'.preg_replace('/[^A-Z0-9_]/', '', strtoupper($field));

        match ($op) {
            'equal' => $q->where($column, $value),
            'not_equal' => $q->where(fn ($t) => $t->whereNull($column)->orWhere($column, '!=', $value)),
            'contains' => $q->where($column, 'like', '%'.static::escapeLike($value).'%'),
            'not_contains' => $q->where(fn ($t) => $t->whereNull($column)->orWhere($column, 'not like', '%'.static::escapeLike($value).'%')),
            'starts' => $q->where($column, 'like', static::escapeLike($value).'%'),
            'ends' => $q->where($column, 'like', '%'.static::escapeLike($value)),
            'blank' => $q->where(fn ($t) => $t->whereNull($column)->orWhere($column, '')),
            'not_blank' => $q->whereNotNull($column)->where($column, '!=', ''),
            'greater' => $q->where($column, '>', is_numeric($value) ? $value + 0 : $value),
            'less' => $q->where($column, '<', is_numeric($value) ? $value + 0 : $value),
            default => null,
        };
    }

    protected static function escapeLike(?string $value): string
    {
        return addcslashes((string) $value, '%_\\');
    }
}

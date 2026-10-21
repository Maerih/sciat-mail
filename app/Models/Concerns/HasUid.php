<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Public identifier used in URLs instead of auto-increment ids.
 */
trait HasUid
{
    public static function bootHasUid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = strtolower(Str::random(13));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public static function findByUid(string $uid): ?static
    {
        return static::where('uid', $uid)->first();
    }
}

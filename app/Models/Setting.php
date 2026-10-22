<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Platform-wide key/value settings (branding etc.), cached. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'platform-settings';

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::all_()[$key] ?? $default;
    }

    /** Boolean flags are stored as '1' / '0'. */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : $value === '1';
    }

    /** @param array<string, ?string> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, ?string> */
    protected static function all_(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }
}

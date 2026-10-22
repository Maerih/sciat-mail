<?php

namespace App\Services;

/** Updates keys in the .env file, keeping everything else (comments, order) intact. */
class EnvWriter
{
    /** @param array<string, string|int|bool|null> $values */
    public static function set(array $values, ?string $path = null): void
    {
        $path ??= app()->environmentFilePath();
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.static::format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $content, LOCK_EX);
    }

    protected static function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $value = (string) $value;

        // Quote anything with spaces, quotes, # or $ so phpdotenv reads it back exactly
        return $value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)
            ? $value
            : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}

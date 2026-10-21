<?php

namespace App\Sending;

/**
 * Expands {option one|option two|option three} groups, nested groups included.
 * The pick is derived from a seed (the delivery uid) so every part of one message —
 * subject, HTML, text, web view — shows the same choice. Merge tags ({{ ... }}), <style>
 * blocks and braces without a "|" (CSS, JSON) are left untouched.
 */
class Spintax
{
    public static function spin(string $text, string $seed): string
    {
        if (! str_contains($text, '|') || ! str_contains($text, '{')) {
            return $text;
        }

        $protected = [];
        $text = preg_replace_callback('#<style\b[^>]*>.*?</style>|\{\{.*?\}\}#is', function ($m) use (&$protected) {
            $protected[] = $m[0];

            return "\x01".(count($protected) - 1)."\x02";
        }, $text);

        $group = 0;
        // innermost groups first, so nested options resolve outward
        do {
            $text = preg_replace_callback('/\{([^{}]*\|[^{}]*)\}/', function ($m) use ($seed, &$group) {
                $options = explode('|', $m[1]);

                return $options[crc32($seed.':'.$group++) % count($options)];
            }, $text, -1, $count);
        } while ($count > 0);

        return preg_replace_callback("/\x01(\d+)\x02/", fn ($m) => $protected[(int) $m[1]], $text);
    }
}

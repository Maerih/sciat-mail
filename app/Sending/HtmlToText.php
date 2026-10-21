<?php

namespace App\Sending;

class HtmlToText
{
    public static function convert(string $html): string
    {
        $html = preg_replace('#<(head|style|script)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace_callback('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
            $text = trim(strip_tags($m[2]));
            $url = html_entity_decode($m[1]);

            return $text === '' || $text === $url ? $url : "{$text} ({$url})";
        }, $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|h[1-6]|li|tr|table)>#i', "\n\n", $html);
        $html = preg_replace('#<li[^>]*>#i', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}

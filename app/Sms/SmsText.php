<?php

namespace App\Sms;

use App\Models\Subscriber;
use App\Sending\MergeTags;

/** Message length rules: GSM-7 = 160 chars (153 per part), anything else (emoji, accents) = UCS-2, 70 (67). */
class SmsText
{
    protected const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    protected const GSM_EXTENDED = '^{}\\[~]|€';

    public static function isGsm(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM, $char) && ! str_contains(self::GSM_EXTENDED, $char)) {
                return false;
            }
        }

        return true;
    }

    /** Characters counted the way carriers do (extended GSM characters take two). */
    public static function length(string $text): int
    {
        if (! static::isGsm($text)) {
            return mb_strlen($text);
        }

        return mb_strlen($text) + collect(mb_str_split($text))->filter(fn ($c) => str_contains(self::GSM_EXTENDED, $c))->count();
    }

    public static function parts(string $text): int
    {
        $length = static::length($text);
        [$single, $multi] = static::isGsm($text) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    public static function render(string $template, ?Subscriber $subscriber, array $extra = []): string
    {
        $values = MergeTags::subscriberValues($subscriber) + ['LIST_NAME' => $subscriber?->list?->name ?? ''] + $extra;

        return trim(MergeTags::render($template, $values, false));
    }
}

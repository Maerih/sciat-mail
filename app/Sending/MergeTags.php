<?php

namespace App\Sending;

use App\Models\MailList;
use App\Models\Subscriber;

/**
 * Replaces {{ TAG }} placeholders. A fallback can be given after a pipe:
 * {{ SUBSCRIBER_FIRST_NAME | there }}.
 */
class MergeTags
{
    /** Tags whose values are trusted HTML rendered by the platform. */
    public const RAW_HTML = ['RSS_ITEMS'];

    public static function render(string $content, array $values, bool $escape = true): string
    {
        return preg_replace_callback('/\{\{\s*([A-Z0-9_]+)\s*(?:\|\s*([^}]*?)\s*)?\}\}/', function ($m) use ($values, $escape) {
            if (! array_key_exists($m[1], $values)) {
                return $m[0];
            }
            $value = (string) ($values[$m[1]] ?? '');
            if ($value === '' && isset($m[2])) {
                $value = $m[2];
            }

            // URLs and pre-rendered HTML blocks are inserted raw; everything else is escaped for HTML bodies
            return $escape && ! str_ends_with($m[1], '_URL') && ! in_array($m[1], self::RAW_HTML, true) ? e($value) : $value;
        }, $content);
    }

    public static function subscriberValues(?Subscriber $subscriber): array
    {
        if (! $subscriber) {
            return [];
        }

        $values = ['SUBSCRIBER_EMAIL' => $subscriber->email, 'SUBSCRIBER_UID' => $subscriber->uid];
        foreach ($subscriber->list->fields as $field) {
            $values['SUBSCRIBER_'.$field->tag] = $subscriber->field($field->tag) ?? $field->default_value ?? '';
        }

        return $values;
    }

    /** Tags offered in editors. */
    public static function catalogue(?MailList $list = null): array
    {
        $tags = [
            'SUBSCRIBER_EMAIL' => 'Subscriber email',
            'UNSUBSCRIBE_URL' => 'Unsubscribe link',
            'WEB_VIEW_URL' => 'View in browser link',
            'LIST_NAME' => 'List name',
            'CAMPAIGN_NAME' => 'Campaign name',
            'CONTACT_ADDRESS' => 'Postal address',
            'CURRENT_YEAR' => 'Current year',
            'RSS_ITEMS' => 'RSS: latest posts (needs a feed)',
            'RSS_LATEST_TITLE' => 'RSS: newest post title',
            'RSS_LATEST_URL' => 'RSS: newest post link',
        ];
        foreach ($list?->fields ?? [] as $field) {
            $tags['SUBSCRIBER_'.$field->tag] = $field->label;
        }

        return $tags;
    }
}

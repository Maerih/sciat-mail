<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Facades\Http;

/**
 * Pre-send content checks: subject, preheader, spam-trigger words, links, images and size.
 * Each check is ['label', 'level' => ok|warning|error, 'message'].
 */
class ContentReview
{
    /** Phrases spam filters and readers associate with junk mail. */
    public const SPAM_PHRASES = [
        'act now', 'buy now', 'click here', 'free money', 'guaranteed', 'risk-free', 'risk free', '100% free', 'no cost', 'winner',
        'congratulations', 'cash bonus', 'earn money', 'make money', 'double your', 'limited time', 'urgent', 'once in a lifetime',
        'no obligation', 'order now', 'special promotion', 'this is not spam', 'viagra', 'casino', 'lowest price', 'apply now',
    ];

    public const GMAIL_CLIP_BYTES = 102 * 1024;

    /** @return array<int, array{label: string, level: string, message: string}> */
    public function review(Campaign $campaign, bool $checkLinks = false): array
    {
        $checks = [];
        $add = function (string $label, string $level, string $message) use (&$checks) {
            $checks[] = compact('label', 'level', 'message');
        };

        $subject = (string) $campaign->subject;
        $length = mb_strlen($subject);
        match (true) {
            $length === 0 => $add('Subject line', 'error', 'The subject line is empty.'),
            $length > 70 => $add('Subject line', 'warning', "{$length} characters — most inboxes cut subjects after about 60. Put the key words first."),
            $length < 10 => $add('Subject line', 'warning', "Only {$length} characters — a few more words usually earn more opens."),
            default => $add('Subject line', 'ok', "{$length} characters — a good length."),
        };
        $letters = preg_replace('/[^A-Za-z]/', '', $subject);
        if (strlen($letters) >= 6 && strtoupper($letters) === $letters) {
            $add('Capital letters', 'warning', 'The subject is all capitals, which reads as shouting and trips spam filters.');
        }
        if (substr_count($subject, '!') > 1) {
            $add('Punctuation', 'warning', 'Several exclamation marks in the subject look spammy — one is plenty.');
        }

        filled($campaign->preheader)
            ? $add('Preheader', 'ok', 'Preview text is set.')
            : $add('Preheader', 'warning', 'No preheader: inboxes will show the first text of the email instead. Add one to boost opens.');

        $html = $campaign->isPlain() ? '' : (string) $campaign->html;
        $text = mb_strtolower($subject.' '.strip_tags($html).' '.$campaign->plain);
        $found = collect(self::SPAM_PHRASES)->filter(fn ($phrase) => str_contains($text, $phrase))->values();
        $found->isEmpty()
            ? $add('Spam-trigger words', 'ok', 'No common spam-trigger phrases found.')
            : $add('Spam-trigger words', $found->count() > 3 ? 'error' : 'warning', 'Consider rewording: “'.$found->implode('”, “').'”.');

        if ($campaign->isPlain()) {
            return $checks;
        }

        $hasUnsubscribe = str_contains($html, 'UNSUBSCRIBE_URL');
        $hasUnsubscribe
            ? $add('Unsubscribe link', 'ok', 'The email contains an unsubscribe link.')
            : $add('Unsubscribe link', 'warning', 'No {{ UNSUBSCRIBE_URL }} link in the content. One-click unsubscribe headers are still added, but a visible link is required by law in most countries.');

        $bytes = strlen($html);
        $bytes > self::GMAIL_CLIP_BYTES
            ? $add('Email size', 'warning', round($bytes / 1024).' KB — Gmail clips messages over 102 KB and hides the rest (including the unsubscribe link).')
            : $add('Email size', 'ok', round($bytes / 1024).' KB — under Gmail\'s 102 KB clipping limit.');

        preg_match_all('/<img\b[^>]*>/i', $html, $images);
        $missingAlt = collect($images[0])->reject(fn ($tag) => preg_match('/\balt\s*=\s*"[^"]+"/i', $tag))->count();
        if (count($images[0]) === 0) {
            $add('Images', 'ok', 'No images — text-only emails load fast everywhere.');
        } else {
            $missingAlt
                ? $add('Image alt text', 'warning', "{$missingAlt} of ".count($images[0]).' images have no alt text. Many inboxes block images until the reader allows them.')
                : $add('Image alt text', 'ok', 'All '.count($images[0]).' images have alt text.');
            $words = str_word_count(strip_tags($html));
            if ($words < 50 && count($images[0]) > 0) {
                $add('Text to image ratio', 'warning', "Only {$words} words of text. Image-heavy emails with little text are often filtered as spam.");
            }
        }

        preg_match_all('/<a\b[^>]*href\s*=\s*"([^"]+)"/i', $html, $matches);
        $links = collect($matches[1])->map(fn ($u) => html_entity_decode(trim($u)))->unique()->values();
        $external = $links->filter(fn ($u) => preg_match('#^https?://#i', $u))->values();
        $insecure = $external->filter(fn ($u) => str_starts_with(strtolower($u), 'http://'))->count();
        $empty = $links->filter(fn ($u) => in_array($u, ['', '#'], true))->count();
        $add('Links', $empty ? 'warning' : 'ok', $links->count().' links'.($empty ? ", {$empty} with an empty or # target" : '').($insecure ? ", {$insecure} not using https" : '').'.');

        if ($checkLinks && $external->isNotEmpty()) {
            $broken = $external->take(25)->filter(function ($url) {
                try {
                    $response = Http::timeout(6)->withUserAgent('Mozilla/5.0 (compatible; LinkCheck/1.0)')->head($url);
                    if ($response->status() === 405) {
                        $response = Http::timeout(6)->get($url);
                    }

                    return $response->status() >= 400;
                } catch (\Throwable) {
                    return true;
                }
            })->values();
            $broken->isEmpty()
                ? $add('Broken links', 'ok', 'All '.min(25, $external->count()).' links responded.')
                : $add('Broken links', 'error', 'Not reachable: '.$broken->implode(', '));
        }

        return $checks;
    }

    /** 0–100: 100 minus 15 per error and 6 per warning. */
    public static function score(array $checks): int
    {
        $levels = collect($checks)->countBy('level');

        return max(0, 100 - 15 * ($levels['error'] ?? 0) - 6 * ($levels['warning'] ?? 0));
    }
}

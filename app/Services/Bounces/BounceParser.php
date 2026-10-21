<?php

namespace App\Services\Bounces;

/**
 * Reads a raw bounce (RFC 3464 DSN or a classic "undeliverable" notice) or a spam
 * complaint (RFC 5965 ARF) and extracts what we need to update the delivery ledger.
 */
class BounceParser
{
    /**
     * @return array{type: string, delivery_uid: ?string, message_id: ?string, recipient: ?string, hard: bool, detail: ?string}|null
     *                                                                                                                               null when the message is not a bounce or complaint (auto-replies, newsletters, spam…)
     */
    public static function parse(string $raw): ?array
    {
        [$headers] = preg_split("/\r?\n\r?\n/", $raw, 2) + [''];
        $headerValue = fn (string $name) => preg_match('/^'.preg_quote($name, '/').':\s*(.+(?:\r?\n[ \t].+)*)/mi', $headers, $m)
            ? trim(preg_replace('/\r?\n[ \t]+/', ' ', $m[1])) : null;

        // Out-of-office and other auto-replies are not bounces
        if (preg_match('/auto-replied|auto-notified/i', (string) $headerValue('Auto-Submitted'))
            && ! preg_match('/report-type=|delivery-status|feedback-report/i', $raw)) {
            return null;
        }

        $deliveryUid = preg_match('/^X-SM-Delivery:\s*([a-z0-9]{24})/mi', $raw, $m) ? strtolower($m[1]) : null;
        $originalMessageId = null;
        if (preg_match_all('/^Message-ID:\s*(<[^>]+>)/mi', $raw, $ids) && count($ids[1]) > 1) {
            $originalMessageId = end($ids[1]); // the first one belongs to the bounce itself
        }
        $recipient = preg_match('/^(?:Final|Original)-Recipient:\s*rfc822;\s*<?([^\s>]+@[^\s>]+)>?/mi', $raw, $m) ? strtolower($m[1]) : null;

        if (preg_match('/^Feedback-Type:\s*(\S+)/mi', $raw, $m) || preg_match('/report-type="?feedback-report/i', $raw)) {
            return [
                'type' => 'complaint', 'delivery_uid' => $deliveryUid, 'message_id' => $originalMessageId,
                'recipient' => $recipient ?? (preg_match('/^Original-Rcpt-To:\s*(\S+@\S+)/mi', $raw, $r) ? strtolower($r[1]) : null),
                'hard' => true, 'detail' => 'Feedback-Type: '.($m[1] ?? 'abuse'),
            ];
        }

        $isDsn = (bool) preg_match('/report-type="?delivery-status/i', $raw);
        $looksLikeBounce = $isDsn || preg_match('/undeliver|delivery (status notification|has failed|failure)|returned mail|mail delivery (failed|subsystem)|failure notice|could not be delivered/i',
            ($headerValue('Subject') ?? '').' '.($headerValue('From') ?? ''));
        if (! $looksLikeBounce) {
            return null;
        }

        // A DSN "Action: delayed" is only a warning, not a bounce
        if (preg_match('/^Action:\s*delayed/mi', $raw) && ! preg_match('/^Action:\s*failed/mi', $raw)) {
            return null;
        }

        $status = preg_match('/^Status:\s*([245])\.(\d{1,3})\.(\d{1,3})/mi', $raw, $s) ? "{$s[1]}.{$s[2]}.{$s[3]}" : null;
        $diagnostic = preg_match('/^Diagnostic-Code:\s*(?:smtp;\s*)?(.+(?:\r?\n[ \t].+)*)/mi', $raw, $d) ? trim(preg_replace('/\s+/', ' ', $d[1])) : null;

        if ($status === null && preg_match('/\b([45])\d\d[ -]([45]\.\d{1,3}\.\d{1,3})?/', $diagnostic ?? self::body($raw), $c)) {
            $status = ! empty($c[2]) ? $c[2] : $c[1].'.0.0';
        }

        // 5.x.x is permanent, except a full mailbox (5.2.2) which usually clears up
        $hard = $status !== null && str_starts_with($status, '5') && $status !== '5.2.2';

        return [
            'type' => 'bounce', 'delivery_uid' => $deliveryUid, 'message_id' => $originalMessageId,
            'recipient' => $recipient, 'hard' => $hard,
            'detail' => mb_substr(trim(($status ? $status.' ' : '').($diagnostic ?? '')), 0, 500) ?: null,
        ];
    }

    protected static function body(string $raw): string
    {
        return mb_substr(preg_split("/\r?\n\r?\n/", $raw, 2)[1] ?? '', 0, 20000);
    }
}

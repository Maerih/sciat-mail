<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Workspace;

/**
 * Per-client limits set by the team: emails per calendar month (in the client's timezone)
 * and total contacts across lists. Null limits mean unlimited.
 */
class Quota
{
    public const WARN_AT = 0.8;

    public static function emailsUsed(Workspace $workspace): int
    {
        $start = now($workspace->timezone)->startOfMonth()->utc();

        return Delivery::where('workspace_id', $workspace->id)->whereIn('kind', ['campaign', 'automation', 'transactional'])
            ->where('sent_at', '>=', $start)->count();
    }

    public static function emailsRemaining(Workspace $workspace): ?int
    {
        return $workspace->monthly_email_limit === null ? null : max(0, $workspace->monthly_email_limit - static::emailsUsed($workspace));
    }

    public static function contactsUsed(Workspace $workspace): int
    {
        return Subscriber::whereIn('mail_list_id', MailList::where('workspace_id', $workspace->id)->select('id'))->count();
    }

    public static function canAddContacts(Workspace $workspace, int $count = 1): bool
    {
        return $workspace->contact_limit === null || static::contactsUsed($workspace) + $count <= $workspace->contact_limit;
    }

    /** @return array{emails: array{used: int, limit: ?int}, contacts: array{used: int, limit: ?int}} */
    public static function summary(Workspace $workspace): array
    {
        return [
            'emails' => ['used' => static::emailsUsed($workspace), 'limit' => $workspace->monthly_email_limit],
            'contacts' => ['used' => static::contactsUsed($workspace), 'limit' => $workspace->contact_limit],
        ];
    }

    public static function contactLimitMessage(Workspace $workspace): string
    {
        return 'This list has reached its contact limit ('.number_format((int) $workspace->contact_limit).' contacts).';
    }
}

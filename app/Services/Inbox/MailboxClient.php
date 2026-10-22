<?php

namespace App\Services\Inbox;

use App\Models\InboxMailbox;
use Illuminate\Support\Carbon;

interface MailboxClient
{
    /** Folders to sync: ['inbox' => 'INBOX', 'sent' => 'INBOX.Sent' or null]. */
    public function folders(InboxMailbox $mailbox): array;

    /**
     * Messages received since $since, oldest first, normalised to:
     * uid, message_id, in_reply_to, references, subject, from_email, from_name, to[], cc[], date,
     * text, html, seen, attachments[] (name, mime, content).
     *
     * @return iterable<array<string, mixed>>
     */
    public function fetch(InboxMailbox $mailbox, string $folder, Carbon $since, int $limit): iterable;

    public function markSeen(InboxMailbox $mailbox, string $folder, int $uid, bool $seen): void;

    /** Saves a copy of a message we sent into the Sent folder (so webmail shows it too). */
    public function appendToSent(InboxMailbox $mailbox, string $rawMessage): void;
}

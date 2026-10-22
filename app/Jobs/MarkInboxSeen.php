<?php

namespace App\Jobs;

use App\Models\InboxMessage;
use App\Services\Inbox\MailboxClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Mirrors read/unread from the team inbox to the IMAP server, so webmail matches. */
class MarkInboxSeen implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public array $messageIds, public bool $seen = true) {}

    public function handle(MailboxClient $client): void
    {
        InboxMessage::with('mailbox')->whereIn('id', $this->messageIds)->whereNotNull('imap_uid')->where('direction', 'in')->get()
            ->each(function (InboxMessage $message) use ($client) {
                try {
                    $client->markSeen($message->mailbox, (string) $message->folder, (int) $message->imap_uid, $this->seen);
                } catch (\Throwable) {
                    // best effort: the local state is what the team sees
                }
            });
    }
}

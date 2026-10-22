<?php

namespace App\Services\Inbox;

use App\Models\InboxMailbox;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Pulls new mail into the team inbox and groups it into conversations. */
class InboxSync
{
    public const FIRST_SYNC_DAYS = 14;

    public const PER_RUN = 200;

    public const MAX_ATTACHMENT = 15 * 1024 * 1024;

    public function __construct(protected MailboxClient $client) {}

    public function syncAll(): int
    {
        return InboxMailbox::where('status', 'active')->get()->sum(fn (InboxMailbox $mailbox) => $this->sync($mailbox));
    }

    /** Returns how many new messages were stored. */
    public function sync(InboxMailbox $mailbox): int
    {
        $since = $mailbox->last_synced_at ? $mailbox->last_synced_at->copy()->subDay() : now()->subDays(self::FIRST_SYNC_DAYS);
        $count = 0;
        try {
            foreach (array_filter($this->client->folders($mailbox)) as $kind => $folder) {
                foreach ($this->client->fetch($mailbox, $folder, $since, self::PER_RUN) as $data) {
                    $count += (int) $this->store($mailbox, $folder, $kind === 'sent' ? 'out' : 'in', $data);
                }
            }
            $mailbox->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
        } catch (\Throwable $e) {
            $mailbox->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }

        return $count;
    }

    /** @param array<string, mixed> $data */
    public function store(InboxMailbox $mailbox, string $folder, string $direction, array $data): bool
    {
        $exists = InboxMessage::where('inbox_mailbox_id', $mailbox->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('folder', $folder)->where('imap_uid', $data['uid']))
                ->when(filled($data['message_id']), fn ($w) => $w->orWhere('message_id', $data['message_id'])))
            ->exists();
        if ($exists) {
            return false; // includes replies we sent from here and then found in Sent
        }

        $counterpart = $direction === 'in' ? ['email' => $data['from_email'], 'name' => $data['from_name']] : ($data['to'][0] ?? ['email' => null, 'name' => null]);
        $thread = $this->threadFor($mailbox, $data, $counterpart);

        $message = InboxMessage::create([
            'inbox_mailbox_id' => $mailbox->id, 'inbox_thread_id' => $thread->id, 'direction' => $direction, 'folder' => $folder, 'imap_uid' => $data['uid'],
            'message_id' => $data['message_id'] ?: null, 'in_reply_to' => $data['in_reply_to'] ?: null, 'references' => $data['references'] ?: null,
            'from_email' => $data['from_email'], 'from_name' => $data['from_name'] ?: null, 'to' => $data['to'], 'cc' => $data['cc'],
            'subject' => mb_substr((string) $data['subject'], 0, 250), 'body_html' => $data['html'], 'body_text' => $data['text'],
            'is_read' => $direction === 'out' || $data['seen'], 'sent_at' => $data['date'],
        ]);
        $message->forceFill(['attachments' => $this->saveAttachments($message, $data['attachments'] ?? [])])->save();

        if ($direction === 'in' && $thread->status === 'done') {
            $thread->status = 'open'; // a new reply reopens a finished conversation
        }
        $thread->refreshSummary();

        return true;
    }

    /** @param array{email: ?string, name: ?string} $counterpart */
    protected function threadFor(InboxMailbox $mailbox, array $data, array $counterpart): InboxThread
    {
        $refs = collect(preg_split('/[\s,]+/', trim(($data['in_reply_to'] ?? '').' '.($data['references'] ?? ''))))
            ->map(fn ($r) => trim($r, '<> '))->filter()->unique()->values();
        if ($refs->isNotEmpty()) {
            $threadId = InboxMessage::where('inbox_mailbox_id', $mailbox->id)->whereIn('message_id', $refs)->value('inbox_thread_id');
            if ($threadId) {
                return InboxThread::find($threadId);
            }
        }

        // No headers to follow: same subject with the same person in the last 30 days
        $subject = InboxThread::normalizeSubject($data['subject'] ?? '');
        if ($subject !== '' && $counterpart['email']) {
            $match = InboxThread::where('inbox_mailbox_id', $mailbox->id)->where('contact_email', $counterpart['email'])
                ->where('last_message_at', '>=', now()->subDays(30))->latest('last_message_at')->get()
                ->first(fn ($t) => InboxThread::normalizeSubject($t->subject) === $subject);
            if ($match) {
                return $match;
            }
        }

        return InboxThread::create([
            'inbox_mailbox_id' => $mailbox->id, 'subject' => mb_substr((string) ($data['subject'] ?: '(no subject)'), 0, 250),
            'contact_email' => $counterpart['email'], 'contact_name' => $counterpart['name'] ?: null, 'last_message_at' => $data['date'] ?? now(),
        ]);
    }

    /** @return array<int, array{name: string, mime: string, size: int, path: string}> */
    protected function saveAttachments(InboxMessage $message, array $attachments): array
    {
        $saved = [];
        foreach ($attachments as $i => $attachment) {
            $content = (string) ($attachment['content'] ?? '');
            if ($content === '' || strlen($content) > self::MAX_ATTACHMENT) {
                continue;
            }
            $name = Str::limit(preg_replace('/[^\w.\- ]+/u', '_', (string) ($attachment['name'] ?: 'attachment-'.($i + 1))), 120, '');
            $path = "inbox/{$message->inbox_mailbox_id}/{$message->uid}/{$i}-{$name}";
            Storage::disk('local')->put($path, $content);
            $saved[] = ['name' => $name, 'mime' => (string) ($attachment['mime'] ?: 'application/octet-stream'), 'size' => strlen($content), 'path' => $path];
        }

        return $saved;
    }
}

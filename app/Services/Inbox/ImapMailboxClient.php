<?php

namespace App\Services\Inbox;

use App\Models\InboxMailbox;
use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapMailboxClient implements MailboxClient
{
    /** @var array<int, Client> */
    protected array $clients = [];

    protected function client(InboxMailbox $mailbox): Client
    {
        if (! isset($this->clients[$mailbox->id])) {
            $client = (new ClientManager)->make([
                'host' => $mailbox->imap_host, 'port' => $mailbox->imap_port,
                'encryption' => $mailbox->imap_encryption === 'none' ? false : $mailbox->imap_encryption,
                'validate_cert' => true, 'username' => $mailbox->username, 'password' => $mailbox->password, 'protocol' => 'imap',
            ]);
            $client->connect();
            $this->clients[$mailbox->id] = $client;
        }

        return $this->clients[$mailbox->id];
    }

    public function folders(InboxMailbox $mailbox): array
    {
        $paths = collect($this->client($mailbox)->getFolders(false))->map(fn ($f) => $f->path);
        $sent = $paths->first(fn ($p) => preg_match('/(^|[.\/])(sent( items| mail| messages)?|gesendet)$/i', $p));

        return ['inbox' => 'INBOX', 'sent' => $sent];
    }

    public function fetch(InboxMailbox $mailbox, string $folder, Carbon $since, int $limit): iterable
    {
        $imapFolder = $this->client($mailbox)->getFolderByPath($folder);
        if (! $imapFolder) {
            return;
        }
        $messages = $imapFolder->query()->since($since)->leaveUnread()->setFetchOrder('asc')->limit($limit)->get();
        foreach ($messages as $message) {
            $address = fn ($attribute) => collect($attribute?->toArray() ?? [])->map(fn ($a) => ['email' => strtolower((string) ($a->mail ?? '')), 'name' => (string) ($a->personal ?? '')])
                ->filter(fn ($a) => $a['email'] !== '')->values()->all();
            $from = $address($message->getFrom())[0] ?? ['email' => null, 'name' => null];

            yield [
                'uid' => (int) $message->getUid(),
                'message_id' => trim((string) $message->getMessageId(), '<> '),
                'in_reply_to' => trim((string) $message->getInReplyTo(), '<> '),
                'references' => (string) $message->getReferences(),
                'subject' => (string) $message->getSubject(),
                'from_email' => $from['email'],
                'from_name' => $from['name'],
                'to' => $address($message->getTo()),
                'cc' => $address($message->getCc()),
                'date' => Carbon::parse((string) $message->getDate()->first()),
                'text' => $message->hasTextBody() ? $message->getTextBody() : null,
                'html' => $message->hasHTMLBody() ? $message->getHTMLBody() : null,
                'seen' => $message->getFlags()->has('seen'),
                'attachments' => collect($message->getAttachments())->map(fn ($a) => ['name' => (string) $a->getName(), 'mime' => (string) $a->getMimeType(), 'content' => $a->getContent()])->all(),
            ];
        }
    }

    public function markSeen(InboxMailbox $mailbox, string $folder, int $uid, bool $seen): void
    {
        $message = $this->client($mailbox)->getFolderByPath($folder)?->query()->getMessageByUid($uid);
        $seen ? $message?->setFlag('Seen') : $message?->unsetFlag('Seen');
    }

    public function appendToSent(InboxMailbox $mailbox, string $rawMessage): void
    {
        $sent = $this->folders($mailbox)['sent'];
        if ($sent) {
            $this->client($mailbox)->getFolderByPath($sent)?->appendMessage($rawMessage, ['\\Seen']);
        }
    }

    public function __destruct()
    {
        foreach ($this->clients as $client) {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }
    }
}

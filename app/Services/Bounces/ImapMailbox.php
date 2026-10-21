<?php

namespace App\Services\Bounces;

use App\Models\BounceHandler;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/** IMAP access through webklex/php-imap (pure PHP, no ext-imap needed). */
class ImapMailbox implements Mailbox
{
    protected ?Client $client = null;

    /** @var array<int|string, Message> */
    protected array $messages = [];

    public function unread(BounceHandler $handler, int $limit): iterable
    {
        $this->client = (new ClientManager)->make([
            'host' => $handler->host,
            'port' => $handler->port,
            'encryption' => $handler->encryption === 'none' ? false : $handler->encryption,
            'validate_cert' => true,
            'username' => $handler->username,
            'password' => $handler->password,
            'protocol' => 'imap',
        ]);
        $this->client->connect();

        $folder = $this->client->getFolderByPath($handler->folder) ?? $this->client->getFolderByPath('INBOX');
        foreach ($folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get() as $message) {
            $this->messages[$message->getUid()] = $message;
            yield ['id' => $message->getUid(), 'raw' => $message->getHeader()->raw."\r\n\r\n".$message->getRawBody()];
        }
    }

    public function markProcessed(BounceHandler $handler, mixed $id, bool $delete): void
    {
        $message = $this->messages[$id] ?? null;
        if (! $message) {
            return;
        }
        $delete ? $message->delete(false) : $message->setFlag('Seen');
    }

    public function close(): void
    {
        if ($this->client) {
            $this->client->expunge();
            $this->client->disconnect();
        }
    }
}

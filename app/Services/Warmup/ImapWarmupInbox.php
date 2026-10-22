<?php

namespace App\Services\Warmup;

use App\Models\WarmupMailbox;
use Webklex\PHPIMAP\ClientManager;

class ImapWarmupInbox implements WarmupInbox
{
    public function scan(WarmupMailbox $mailbox, array $tokens): array
    {
        $client = (new ClientManager)->make([
            'host' => $mailbox->host, 'port' => $mailbox->port, 'encryption' => $mailbox->encryption === 'none' ? false : $mailbox->encryption,
            'validate_cert' => true, 'username' => $mailbox->username, 'password' => $mailbox->password, 'protocol' => 'imap',
        ]);
        $client->connect();
        $wanted = array_flip($tokens);
        $found = [];

        try {
            $folders = collect($client->getFolders(false))
                ->filter(fn ($folder) => strtoupper($folder->path) === 'INBOX' || preg_match('/spam|junk|bulk/i', $folder->path));
            foreach ($folders as $folder) {
                $isSpam = strtoupper($folder->path) !== 'INBOX';
                foreach ($folder->query()->since(now()->subDays(3))->limit(300)->get() as $message) {
                    $text = (string) ($message->getTextBody() ?: strip_tags((string) $message->getHTMLBody()));
                    if (! preg_match('/Ref:\s*([A-Z0-9]{10})\b/', $text, $m) || ! isset($wanted[$m[1]]) || isset($found[$m[1]])) {
                        continue;
                    }
                    $found[$m[1]] = $isSpam ? 'spam' : 'inbox';
                    $message->setFlag(['Seen', 'Flagged']);
                    if ($isSpam) {
                        $message->move('INBOX');
                    }
                }
            }
        } finally {
            $client->disconnect();
        }

        return $found;
    }
}

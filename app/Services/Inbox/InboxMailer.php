<?php

namespace App\Services\Inbox;

use App\Models\InboxMailbox;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\User;
use App\Sending\HtmlToText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Sends replies and new messages from a team mailbox through its own SMTP server. */
class InboxMailer
{
    public function __construct(protected MailboxClient $client, protected ?TransportInterface $transport = null) {}

    /**
     * @param  string[]  $to
     * @param  string[]  $cc
     * @param  UploadedFile[]  $files
     */
    public function send(InboxMailbox $mailbox, User $user, array $to, array $cc, string $subject, string $body, array $files = [], ?InboxThread $thread = null): InboxMessage
    {
        $parent = $thread?->messages()->reorder()->whereNotNull('message_id')->latest('sent_at')->first();
        $domain = substr(strrchr($mailbox->email, '@') ?: '@localhost', 1);
        $messageId = Str::random(24).'@'.$domain;

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6">'.nl2br(e($body))
            .(filled($mailbox->signature) ? '<br><br>'.$mailbox->signature : '').'</div>';
        if ($parent) {
            $html .= '<br><div style="color:#6b7280;font-size:13px">On '.$parent->sent_at?->format('D, j M Y H:i').', '.e($parent->from_name ?: $parent->from_email).' wrote:</div>'
                .'<blockquote style="margin:4px 0 0;padding-left:12px;border-left:3px solid #e5e7eb;color:#4b5563">'.($parent->body_html ?: nl2br(e((string) $parent->body_text))).'</blockquote>';
        }

        $email = (new Email)
            ->from(new Address($mailbox->email, $mailbox->from_name ?: $mailbox->name))
            ->to(...$to)->subject($subject)->html($html)->text(HtmlToText::convert($html));
        if ($cc) {
            $email->cc(...$cc);
        }
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        if ($parent) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $parent->message_id);
            $email->getHeaders()->addIdHeader('References', array_values(array_filter([...preg_split('/[\s,]+/', trim((string) $parent->references, ' ')), $parent->message_id], fn ($r) => trim($r, '<> ') !== '')));
        }
        foreach ($files as $file) {
            $email->attachFromPath($file->getRealPath(), $file->getClientOriginalName(), $file->getMimeType());
        }

        ($this->transport ?? $this->transportFor($mailbox))->send($email);
        try {
            $this->client->appendToSent($mailbox, $email->toString());
        } catch (\Throwable) {
            // the message went out; a missing Sent copy is not worth failing over
        }

        $thread ??= InboxThread::create(['inbox_mailbox_id' => $mailbox->id, 'subject' => $subject, 'contact_email' => strtolower($to[0]), 'last_message_at' => now()]);
        $message = InboxMessage::create([
            'inbox_mailbox_id' => $mailbox->id, 'inbox_thread_id' => $thread->id, 'user_id' => $user->id, 'direction' => 'out',
            'message_id' => $messageId, 'in_reply_to' => $parent?->message_id, 'references' => $parent ? trim($parent->references.' '.$parent->message_id) : null,
            'from_email' => $mailbox->email, 'from_name' => $mailbox->from_name ?: $mailbox->name,
            'to' => array_map(fn ($e) => ['email' => strtolower($e), 'name' => ''], $to), 'cc' => array_map(fn ($e) => ['email' => strtolower($e), 'name' => ''], $cc),
            'subject' => $subject, 'body_html' => $html, 'body_text' => $body, 'is_read' => true, 'sent_at' => now(),
        ]);
        $saved = [];
        foreach ($files as $i => $file) {
            $path = $file->storeAs("inbox/{$mailbox->id}/{$message->uid}", $i.'-'.preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 'local');
            $saved[] = ['name' => $file->getClientOriginalName(), 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'path' => $path];
        }
        $message->forceFill(['attachments' => $saved])->save();
        $thread->forceFill(['status' => 'open'])->save();
        $thread->refreshSummary();

        return $message;
    }

    public function transportFor(InboxMailbox $mailbox): TransportInterface
    {
        return (new EsmtpTransportFactory)->create(new Dsn(
            $mailbox->smtp_encryption === 'ssl' ? 'smtps' : 'smtp', $mailbox->smtp_host, $mailbox->username, $mailbox->password, $mailbox->smtp_port,
            $mailbox->smtp_encryption === 'none' ? ['auto_tls' => 'false'] : []
        ));
    }
}

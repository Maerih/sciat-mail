<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use App\Sending\HtmlToText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    use HasUid;

    protected $fillable = ['inbox_mailbox_id', 'inbox_thread_id', 'user_id', 'direction', 'folder', 'imap_uid', 'message_id', 'in_reply_to', 'references',
        'from_email', 'from_name', 'to', 'cc', 'subject', 'body_html', 'body_text', 'attachments', 'is_read', 'sent_at'];

    protected function casts(): array
    {
        return ['to' => 'array', 'cc' => 'array', 'attachments' => 'array', 'is_read' => 'boolean', 'sent_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'inbox_thread_id');
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(InboxMailbox::class, 'inbox_mailbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::whereIn('inbox_mailbox_id', InboxMailbox::visibleTo(request()->user())->select('id'))->where($field ?? 'uid', $value)->firstOrFail();
    }

    public function plainText(): string
    {
        return filled($this->body_text) ? (string) $this->body_text : HtmlToText::convert((string) $this->body_html);
    }

    /** Message HTML for a sandboxed iframe: no scripts, links open in a new tab. */
    public function safeHtml(): string
    {
        $html = filled($this->body_html) ? (string) $this->body_html : '<pre style="white-space:pre-wrap;font:14px/1.6 system-ui,sans-serif;margin:0">'.e((string) $this->body_text).'</pre>';
        $html = preg_replace('#<(script|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $csp = "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; img-src * data: cid:; style-src 'unsafe-inline' *; font-src *\">";

        return '<!doctype html><html><head><meta charset="utf-8">'.$csp.'<base target="_blank"><style>body{margin:0;padding:4px;font:14px/1.6 system-ui,-apple-system,sans-serif;color:#1f2937;word-wrap:break-word}img{max-width:100%;height:auto}</style></head><body>'.$html.'</body></html>';
    }
}

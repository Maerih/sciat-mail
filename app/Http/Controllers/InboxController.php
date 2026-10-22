<?php

namespace App\Http\Controllers;

use App\Jobs\MarkInboxSeen;
use App\Models\InboxMailbox;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Inbox\InboxMailer;
use App\Services\Inbox\InboxSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Team inbox: read and answer the company mailboxes without webmail. */
class InboxController extends Controller
{
    public const VIEWS = ['open' => 'Inbox', 'unread' => 'Unread', 'starred' => 'Starred', 'mine' => 'Assigned to me', 'sent' => 'Sent', 'done' => 'Done'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $mailboxes = InboxMailbox::visibleTo($user)->orderBy('name')->get();
        $mailbox = $mailboxes->firstWhere('uid', $request->query('mailbox'));
        $view = array_key_exists($request->query('view'), self::VIEWS) ? $request->query('view') : 'open';
        $ids = $mailbox ? [$mailbox->id] : $mailboxes->pluck('id')->all();

        $threads = InboxThread::with('mailbox:id,uid,name,color,email', 'assignee:id,name')->whereIn('inbox_mailbox_id', $ids)
            ->when($view === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($view === 'unread', fn ($q) => $q->where('unread', '>', 0))
            ->when($view === 'starred', fn ($q) => $q->where('starred', true))
            ->when($view === 'mine', fn ($q) => $q->where('assigned_user_id', $user->id)->where('status', 'open'))
            ->when($view === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($view === 'sent', fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('direction', 'out')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $term)->orWhere('contact_email', 'like', $term)->orWhere('contact_name', 'like', $term)
                    ->orWhereHas('messages', fn ($m) => $m->where('body_text', 'like', $term)));
            })
            ->orderByDesc('last_message_at')->paginate(30)->withQueryString();

        $thread = $request->filled('thread') ? InboxThread::whereIn('inbox_mailbox_id', $mailboxes->pluck('id'))->where('uid', $request->thread)->first() : null;
        $contact = null;
        if ($thread) {
            $unread = $thread->messages()->where('direction', 'in')->where('is_read', false)->pluck('id')->all();
            if ($unread) {
                InboxMessage::whereIn('id', $unread)->update(['is_read' => true]);
                $thread->refreshSummary();
                MarkInboxSeen::dispatch($unread);
            }
            $thread->load('messages.user:id,name', 'mailbox', 'assignee');
            $contact = $thread->contact_email ? Subscriber::with('list:id,uid,name,workspace_id', 'list.workspace:id,name')->where('email', $thread->contact_email)->first() : null;
        }

        return view('inbox.index', [
            'mailboxes' => $mailboxes, 'mailbox' => $mailbox, 'view' => $view, 'threads' => $threads, 'thread' => $thread, 'contact' => $contact,
            'unread' => InboxThread::whereIn('inbox_mailbox_id', $mailboxes->pluck('id'))->where('status', 'open')->where('unread', '>', 0)
                ->selectRaw('inbox_mailbox_id, count(*) as c')->groupBy('inbox_mailbox_id')->pluck('c', 'inbox_mailbox_id'),
            'team' => User::whereIn('role', ['admin', 'member'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, InboxThread $thread, InboxMailer $mailer): RedirectResponse
    {
        $data = $this->validateMessage($request);
        $to = $this->emails($data['to'] ?? '') ?: array_filter([$thread->contact_email]);
        abort_if(! $to, 422);

        return $this->deliver(fn () => $mailer->send($thread->mailbox, $request->user(), $to, $this->emails($data['cc'] ?? ''),
            $this->replySubject($thread->subject), $data['body'], $request->file('files', []), $thread), $thread);
    }

    public function compose(Request $request, InboxMailer $mailer): RedirectResponse
    {
        $data = $this->validateMessage($request) + $request->validate([
            'mailbox' => 'required|string',
            'to' => 'required|string|max:1000',
            'subject' => 'required|string|max:250',
        ]);
        $mailbox = InboxMailbox::visibleTo($request->user())->where('uid', $data['mailbox'])->firstOrFail();
        $to = $this->emails($data['to']);
        if (! $to) {
            return back()->withInput()->with('error', 'Enter at least one valid recipient.');
        }

        return $this->deliver(fn () => $mailer->send($mailbox, $request->user(), $to, $this->emails($data['cc'] ?? ''), $data['subject'], $data['body'], $request->file('files', [])), null);
    }

    public function update(Request $request, InboxThread $thread): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['star', 'unstar', 'done', 'reopen', 'unread', 'assign'])],
            'user_id' => 'nullable|integer|exists:users,id',
        ]);
        match ($data['action']) {
            'star', 'unstar' => $thread->forceFill(['starred' => $data['action'] === 'star'])->save(),
            'done', 'reopen' => $thread->forceFill(['status' => $data['action'] === 'done' ? 'done' : 'open'])->save(),
            'assign' => $thread->forceFill(['assigned_user_id' => $data['user_id'] ?? null])->save(),
            'unread' => $this->markUnread($thread),
        };

        $stay = in_array($data['action'], ['star', 'unstar', 'assign'], true);

        return redirect()->route('inbox.index', array_filter(['mailbox' => $request->query('mailbox'), 'view' => $request->query('view'), 'thread' => $stay ? $thread->uid : null]))
            ->with('success', ['done' => 'Marked as done.', 'reopen' => 'Conversation reopened.', 'unread' => 'Marked as unread.', 'assign' => 'Assignment updated.'][$data['action']] ?? null);
    }

    public function attachment(InboxMessage $message, int $index): StreamedResponse
    {
        abort_unless(InboxMailbox::visibleTo(request()->user())->whereKey($message->inbox_mailbox_id)->exists(), 404);
        $file = ($message->attachments ?? [])[$index] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    public function sync(Request $request, InboxSync $sync): RedirectResponse
    {
        $count = InboxMailbox::visibleTo($request->user())->where('status', 'active')->get()->sum(fn ($mailbox) => $sync->sync($mailbox));

        return back()->with('success', $count ? "{$count} new message(s)." : 'No new messages.');
    }

    protected function markUnread(InboxThread $thread): void
    {
        $last = $thread->messages()->reorder()->where('direction', 'in')->latest('sent_at')->first();
        if ($last) {
            $last->forceFill(['is_read' => false])->save();
            MarkInboxSeen::dispatch([$last->id], false);
        }
        $thread->refreshSummary();
    }

    protected function deliver(\Closure $send, ?InboxThread $thread): RedirectResponse
    {
        try {
            $message = $send();
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Sending failed: '.$e->getMessage());
        }

        return redirect()->route('inbox.index', ['thread' => $message->thread->uid])->with('success', 'Sent.');
    }

    /** @return array<string, mixed> */
    protected function validateMessage(Request $request): array
    {
        return $request->validate([
            'to' => 'nullable|string|max:1000',
            'cc' => 'nullable|string|max:1000',
            'body' => 'required|string|max:50000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240',
        ]);
    }

    /** @return string[] */
    protected function emails(string $list): array
    {
        return collect(preg_split('/[\s,;]+/', $list))->map(fn ($e) => strtolower(trim($e, '<> ')))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }

    protected function replySubject(?string $subject): string
    {
        return preg_match('/^re:/i', (string) $subject) ? (string) $subject : 'Re: '.$subject;
    }
}

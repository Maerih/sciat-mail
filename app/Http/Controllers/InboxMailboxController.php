<?php

namespace App\Http\Controllers;

use App\Models\InboxMailbox;
use App\Models\User;
use App\Services\Inbox\InboxMailer;
use App\Services\Inbox\MailboxClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Administrators connect company mailboxes and choose who can see them. */
class InboxMailboxController extends Controller
{
    public function index(): View
    {
        return view('inbox.mailboxes', [
            'mailboxes' => InboxMailbox::with('members:id,name')->withCount(['threads as open_count' => fn ($q) => $q->where('status', 'open')])->orderBy('name')->get(),
            'team' => User::whereIn('role', ['admin', 'member'])->orderBy('name')->get(['id', 'name', 'email']),
            'editing' => request()->query('edit') ? InboxMailbox::with('members')->where('uid', request()->query('edit'))->first() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mailbox = InboxMailbox::create($this->validated($request));
        $mailbox->members()->sync($request->input('members', []));

        return redirect()->route('inbox.mailboxes.index')->with('success', 'Mailbox added. Use “Test” to check the connection — new mail appears within a minute.');
    }

    public function update(Request $request, InboxMailbox $mailbox): RedirectResponse
    {
        $mailbox->update($this->validated($request, $mailbox));
        $mailbox->members()->sync($request->input('members', []));

        return redirect()->route('inbox.mailboxes.index')->with('success', 'Mailbox saved.');
    }

    public function destroy(InboxMailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

        return redirect()->route('inbox.mailboxes.index')->with('success', 'Mailbox removed from the team inbox. Nothing was deleted on the mail server.');
    }

    public function test(InboxMailbox $mailbox, MailboxClient $client, InboxMailer $mailer): RedirectResponse
    {
        try {
            $folders = $client->folders($mailbox);
        } catch (\Throwable $e) {
            return back()->with('error', 'IMAP (incoming) failed: '.$e->getMessage());
        }
        try {
            $transport = $mailer->transportFor($mailbox);
            $transport->start();
            $transport->stop();
        } catch (\Throwable $e) {
            return back()->with('error', 'IMAP works, but SMTP (outgoing) failed: '.$e->getMessage());
        }

        return back()->with('success', 'Connected. Incoming and outgoing mail work'.($folders['sent'] ? ' — replies are saved to “'.$folders['sent'].'”.' : '.'));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?InboxMailbox $existing = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'from_name' => 'nullable|string|max:120',
            'imap_host' => 'required|string|max:190',
            'imap_port' => 'required|integer|between:1,65535',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'smtp_host' => 'required|string|max:190',
            'smtp_port' => 'required|integer|between:1,65535',
            'smtp_encryption' => 'required|in:ssl,tls,none',
            'username' => 'required|string|max:190',
            'password' => [$existing ? 'nullable' : 'required', 'string', 'max:500'],
            'signature' => 'nullable|string|max:5000',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status' => 'required|in:active,inactive',
            'members' => 'nullable|array',
            'members.*' => 'integer|exists:users,id',
        ]);
        unset($data['members']);
        if ($existing && blank($data['password'] ?? null)) {
            unset($data['password']); // blank keeps the stored password
        }
        $data['color'] ??= '#22c55e';

        return $data;
    }
}

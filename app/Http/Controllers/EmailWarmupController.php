<?php

namespace App\Http\Controllers;

use App\Models\SendingServer;
use App\Models\WarmupEmail;
use App\Models\WarmupMailbox;
use App\Models\Workspace;
use App\Services\Warmup\EmailWarmup;
use App\Services\Warmup\WarmupInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailWarmupController extends Controller
{
    public function index(): View
    {
        $workspace = Workspace::current();
        $servers = $workspace->availableServers()->orderBy('name')->get();
        $since = now()->subDays(13)->startOfDay();
        $daily = WarmupEmail::whereIn('sending_server_id', $servers->pluck('id'))->where('sent_at', '>=', $since)
            ->selectRaw("date(sent_at) as day, count(*) as sent, sum(placement = 'inbox') as inbox, sum(placement = 'spam') as spam")
            ->groupBy('day')->get()->keyBy('day');

        return view('sending.email-warmup', [
            'servers' => $servers,
            'stats' => $servers->mapWithKeys(fn ($s) => [$s->id => EmailWarmup::stats($s)]),
            'mailboxes' => WarmupMailbox::forWorkspace()->withCount(['emails as received' => fn ($q) => $q->whereIn('placement', ['inbox', 'spam'])])->orderBy('email')->get(),
            'chart' => collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString())->map(fn ($day) => [
                'day' => substr($day, 5), 'sent' => (int) ($daily[$day]->sent ?? 0), 'inbox' => (int) ($daily[$day]->inbox ?? 0), 'spam' => (int) ($daily[$day]->spam ?? 0),
            ]),
            'recent' => WarmupEmail::with('mailbox:id,email', 'server:id,name')->whereIn('sending_server_id', $servers->pluck('id'))->latest('id')->limit(15)->get(),
        ]);
    }

    public function storeMailbox(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'host' => 'required|string|max:190',
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'required|in:ssl,tls,none',
            'username' => 'required|string|max:190',
            'password' => 'required|string|max:500',
        ]);
        $mailbox = new WarmupMailbox($data + ['status' => 'active']);
        $mailbox->workspace_id = Workspace::current()->id;
        $mailbox->save();

        return back()->with('success', 'Seed inbox added. Use “Test” to check the IMAP login.');
    }

    public function testMailbox(WarmupMailbox $mailbox, WarmupInbox $inbox): RedirectResponse
    {
        try {
            $inbox->scan($mailbox, []);
            $mailbox->forceFill(['last_checked_at' => now(), 'last_error' => null])->save();

            return back()->with('success', 'Connected to '.$mailbox->email.' — IMAP works.');
        } catch (\Throwable $e) {
            $mailbox->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 250)])->save();

            return back()->with('error', 'Could not connect: '.$e->getMessage());
        }
    }

    public function destroyMailbox(WarmupMailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

        return back()->with('success', 'Seed inbox removed.');
    }

    public function updateServer(Request $request, SendingServer $server): RedirectResponse
    {
        abort_if($server->isShared() && ! $request->user()->isAdmin(), 403);
        $data = $request->validate(['from_email' => 'required_if:enabled,1|nullable|email', 'daily_max' => 'required|integer|between:5,500']);
        $enabled = $request->boolean('enabled');
        $server->forceFill(['email_warmup' => [
            'enabled' => $enabled,
            'from_email' => $data['from_email'] ?? null,
            'daily_max' => (int) $data['daily_max'],
            // the ramp restarts only when warmup is switched on again
            'started_on' => $enabled ? ($server->emailWarmup('enabled') ? $server->emailWarmup('started_on') : now()->toDateString()) : null,
        ]])->save();

        return back()->with('success', $enabled ? 'Email warmup is on for '.$server->name.'.' : 'Email warmup turned off for '.$server->name.'.');
    }
}

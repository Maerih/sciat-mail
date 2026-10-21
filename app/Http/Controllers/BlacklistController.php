<?php

namespace App\Http\Controllers;

use App\Models\Blacklist;
use App\Models\Subscriber;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlacklistController extends Controller
{
    public function index(Request $request): View
    {
        $entries = Blacklist::query()
            ->where(fn ($q) => $q->where('workspace_id', Workspace::current()->id)->orWhereNull('workspace_id'))
            ->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.$request->q.'%'))
            ->when(isset(Blacklist::REASONS[$request->reason]), fn ($q) => $q->where('reason', $request->reason))
            ->latest()->paginate(50)->withQueryString();

        return view('sending.blacklist', compact('entries'));
    }

    /** Accepts one or many addresses (one per line or comma separated). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['emails' => 'required|string|max:200000', 'global' => 'boolean']);
        $global = $request->boolean('global') && $request->user()->isAdmin();

        $emails = collect(preg_split('/[\s,;]+/', $data['emails']))
            ->map(fn ($e) => strtolower(trim($e)))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique();

        $emails->each(fn ($email) => Blacklist::add($email, $global ? null : Workspace::current()->id, 'manual'));

        return back()->with('success', number_format($emails->count()).' address(es) blacklisted.');
    }

    public function destroy(Request $request, Blacklist $blacklist): RedirectResponse
    {
        abort_if($blacklist->workspace_id === null ? ! $request->user()->isAdmin() : $blacklist->workspace_id !== Workspace::current()->id, 403);

        // Restore affected subscriptions to "unsubscribed" — never silently back to subscribed
        Subscriber::where('email', $blacklist->email)->where('status', 'blacklisted')
            ->when($blacklist->workspace_id, fn ($q) => $q->whereHas('list', fn ($l) => $l->where('workspace_id', $blacklist->workspace_id)))
            ->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        $blacklist->delete();

        return back()->with('success', $blacklist->email.' removed from the blacklist.');
    }
}

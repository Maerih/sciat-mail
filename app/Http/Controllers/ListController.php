<?php

namespace App\Http\Controllers;

use App\Models\MailList;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListController extends Controller
{
    public function index(Request $request): View
    {
        $lists = MailList::forWorkspace()
            ->withCount([
                'subscribers',
                'subscribers as subscribed_count' => fn ($q) => $q->where('status', 'subscribed'),
                'segments',
            ])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->orderBy($request->sort === 'name' ? 'name' : 'created_at', $request->sort === 'name' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();

        return view('lists.index', compact('lists'));
    }

    public function create(): View
    {
        return view('lists.form', ['list' => new MailList([
            'from_name' => Workspace::current()->company ?: Workspace::current()->name,
            'from_email' => Workspace::current()->contact_email,
        ]), 'servers' => Workspace::current()->availableServers()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $list = MailList::create($this->validated($request) + ['workspace_id' => Workspace::current()->id]);

        return redirect()->route('lists.show', $list)->with('success', 'List created.');
    }

    public function show(MailList $list): View
    {
        $counts = $list->statusCounts();
        $growth = $list->subscribers()->toBase()
            ->selectRaw('date(created_at) as d, count(*) as c')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('d')->pluck('c', 'd');
        $days = collect(range(29, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->toDateString() => (int) ($growth[now()->subDays($i)->toDateString()] ?? 0)]);

        $engagement = $list->subscribers()->toBase()
            ->join('deliveries', 'deliveries.subscriber_id', '=', 'subscribers.id')
            ->where('deliveries.kind', 'campaign')->whereNotNull('deliveries.sent_at')
            ->selectRaw('count(*) as sent, sum(deliveries.opened_at is not null) as opened, sum(deliveries.clicked_at is not null) as clicked')
            ->first();

        return view('lists.show', [
            'list' => $list,
            'counts' => $counts,
            'total' => array_sum($counts),
            'days' => $days,
            'openRate' => $engagement->sent ? round($engagement->opened / $engagement->sent * 100, 1) : 0,
            'clickRate' => $engagement->sent ? round($engagement->clicked / $engagement->sent * 100, 1) : 0,
        ]);
    }

    public function edit(MailList $list): View
    {
        return view('lists.form', ['list' => $list, 'servers' => Workspace::current()->availableServers()->orderBy('name')->get()]);
    }

    public function update(Request $request, MailList $list): RedirectResponse
    {
        $list->update($this->validated($request));

        return redirect()->route('lists.show', $list)->with('success', 'List settings saved.');
    }

    public function destroy(MailList $list): RedirectResponse
    {
        $list->delete();

        return redirect()->route('lists.index')->with('success', 'List deleted.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'from_name' => 'required|string|max:190',
            'from_email' => 'required|email',
            'reply_to' => 'nullable|email',
            'company' => 'nullable|string|max:190',
            'address' => 'nullable|string|max:255',
            'default_sending_server_id' => 'nullable|integer',
            'notification_email' => 'nullable|email',
            'welcome_subject' => 'nullable|string|max:255',
            'welcome_html' => 'nullable|string',
        ]);

        if (! empty($data['default_sending_server_id'])
            && ! Workspace::current()->availableServers()->whereKey($data['default_sending_server_id'])->exists()) {
            $data['default_sending_server_id'] = null;
        }

        return $data + [
            'double_optin' => $request->boolean('double_optin'),
            'send_welcome_email' => $request->boolean('send_welcome_email'),
            'unsubscribe_notification' => $request->boolean('unsubscribe_notification'),
        ];
    }
}

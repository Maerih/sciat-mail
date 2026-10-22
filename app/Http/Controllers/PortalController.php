<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Workspace;
use App\Services\Quota;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only portal for a client's own users: results of sent campaigns and audience
 * growth. Nothing here can send, edit or reveal individual contacts.
 */
class PortalController extends Controller
{
    public function dashboard(): View
    {
        $workspace = Workspace::current();
        $since = now()->subDays(29)->startOfDay();

        $sent = Delivery::where('workspace_id', $workspace->id)->whereIn('kind', ['campaign', 'automation'])->where('sent_at', '>=', $since)->toBase()
            ->selectRaw('count(*) as sent, sum(opened_at is not null) as opened, sum(clicked_at is not null) as clicked')->first();
        $rate = fn ($n) => $sent->sent ? round($n / $sent->sent * 100, 1) : 0;

        return view('portal.dashboard', [
            'workspace' => $workspace,
            'sent' => (int) $sent->sent,
            'openRate' => $rate($sent->opened),
            'clickRate' => $rate($sent->clicked),
            'subscribers' => Subscriber::whereIn('mail_list_id', MailList::forWorkspace()->select('id'))->where('status', 'subscribed')->count(),
            'recent' => $this->launched()->latest('started_at')->limit(5)->get(),
            'quota' => Quota::summary($workspace),
        ]);
    }

    public function campaigns(): View
    {
        return view('portal.campaigns', ['campaigns' => $this->launched()->latest('started_at')->paginate(20)]);
    }

    public function campaign(Campaign $campaign): View
    {
        abort_if($campaign->isAutomationEmail() || in_array($campaign->status, ['draft', 'scheduled'], true), 404);

        $topLinks = $campaign->events()->where('type', 'click')
            ->selectRaw('url, count(distinct delivery_id) as unique_clicks')->groupBy('url')->orderByDesc('unique_clicks')->limit(10)->get();
        $breakdown = fn (string $column) => $campaign->events()->toBase()->whereIn('type', ['open', 'click'])->whereNotNull($column)
            ->selectRaw("{$column} as label, count(distinct delivery_id) as readers")->groupBy($column)->orderByDesc('readers')->limit(6)->pluck('readers', 'label');

        return view('portal.campaign', [
            'campaign' => $campaign,
            'stats' => $campaign->stats(),
            'topLinks' => $topLinks,
            'breakdowns' => ['Email app' => $breakdown('client'), 'Device' => $breakdown('device'), 'Country' => $breakdown('country')],
        ]);
    }

    public function lists(): View
    {
        $lists = MailList::forWorkspace()->orderBy('name')->get()->map(fn (MailList $list) => [
            'list' => $list,
            'counts' => $list->statusCounts(),
            'new30' => $list->subscribers()->where('created_at', '>=', now()->subDays(30))->count(),
        ]);

        return view('portal.lists', compact('lists'));
    }

    public function account(Request $request): View
    {
        return view('portal.account', ['user' => $request->user()]);
    }

    protected function launched()
    {
        return Campaign::forWorkspace()->regular()->whereNotIn('status', ['draft', 'scheduled']);
    }
}

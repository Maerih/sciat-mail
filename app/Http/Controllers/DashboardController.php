<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\Workspace;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Bounce and complaint rates above these are flagged as unhealthy (industry guidance). */
    public const BOUNCE_WARNING = 2.0;

    public const COMPLAINT_WARNING = 0.1;

    public function index(): View
    {
        $workspace = Workspace::current();
        $since = now()->subDays(29)->startOfDay();
        $listIds = MailList::forWorkspace()->pluck('id');

        $audience = Subscriber::whereIn('mail_list_id', $listIds)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $sent = Delivery::where('workspace_id', $workspace->id)->where('kind', 'campaign')->whereNotNull('sent_at')
            ->where('sent_at', '>=', $since)->toBase()
            ->selectRaw("count(*) as sent, sum(opened_at is not null) as opened, sum(clicked_at is not null) as clicked,
                sum(status = 'bounced') as bounced, sum(status = 'complained') as complained")->first();

        $daily = Delivery::where('workspace_id', $workspace->id)->where('kind', 'campaign')->where('sent_at', '>=', $since)->toBase()
            ->selectRaw('date(sent_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $dailyOpens = DeliveryEvent::where('type', 'open')->where('created_at', '>=', $since)
            ->whereIn('campaign_id', Campaign::forWorkspace()->select('id'))->toBase()
            ->selectRaw('date(created_at) as d, count(distinct delivery_id) as c')->groupBy('d')->pluck('c', 'd');

        $labels = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $rate = fn ($n, $of) => $of > 0 ? round($n / $of * 100, 2) : 0;
        $sentCount = (int) $sent->sent;

        return view('dashboard', [
            'workspace' => $workspace,
            'listCount' => $listIds->count(),
            'campaignCount' => Campaign::forWorkspace()->count(),
            'audience' => $audience,
            'subscriberTotal' => $audience->sum(),
            'sent' => $sentCount,
            'openRate' => $rate($sent->opened, $sentCount),
            'clickRate' => $rate($sent->clicked, $sentCount),
            'bounceRate' => $rate($sent->bounced, $sentCount),
            'complaintRate' => $rate($sent->complained, $sentCount),
            'chart' => [
                'labels' => $labels->map(fn ($d) => date('M j', strtotime($d)))->all(),
                'sent' => $labels->map(fn ($d) => (int) ($daily[$d] ?? 0))->all(),
                'opens' => $labels->map(fn ($d) => (int) ($dailyOpens[$d] ?? 0))->all(),
            ],
            'recentCampaigns' => Campaign::forWorkspace()->latest()->limit(6)->get(),
            'serverCount' => $workspace->availableServers()->where('status', 'active')->count(),
            'verifiedDomains' => $workspace->sendingDomains()->where('ownership_verified', true)->where('dkim_verified', true)->count(),
        ]);
    }
}

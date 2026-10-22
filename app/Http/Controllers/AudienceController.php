<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\MailList;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Audience-wide views across all of the client's lists. */
class AudienceController extends Controller
{
    public function overview(): View
    {
        $lists = MailList::forWorkspace()->withCount(['subscribers', 'subscribers as subscribed_count' => fn ($q) => $q->where('status', 'subscribed')])
            ->orderByDesc('subscribers_count')->get();
        $listIds = $lists->pluck('id');
        $subscribers = Subscriber::whereIn('mail_list_id', $listIds);

        $counts = (clone $subscribers)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $counts = collect(Subscriber::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]);

        $months = collect(range(5, 0))->map(fn ($m) => now()->startOfMonth()->subMonths($m));
        $monthly = (clone $subscribers)->where('created_at', '>=', $months->first())
            ->selectRaw("date_format(created_at, '%Y-%m') as month, count(*) as total")->groupBy('month')->pluck('total', 'month');

        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());
        $daily = (clone $subscribers)->where('created_at', '>=', $days->first())
            ->selectRaw('date(created_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');

        // Unique open/click rates per list from sent campaign deliveries
        $engagement = Delivery::query()->join('subscribers', 'subscribers.id', '=', 'deliveries.subscriber_id')
            ->whereIn('subscribers.mail_list_id', $listIds)->whereNotNull('deliveries.sent_at')
            ->select('subscribers.mail_list_id', DB::raw('count(*) as sent'), DB::raw('count(deliveries.opened_at) as opened'), DB::raw('count(deliveries.clicked_at) as clicked'))
            ->groupBy('subscribers.mail_list_id')->get()->keyBy('mail_list_id');

        return view('audience.overview', [
            'lists' => $lists,
            'counts' => $counts,
            'total' => $counts->sum(),
            'growth' => ['labels' => $months->map->format('M Y'), 'values' => $months->map(fn (Carbon $m) => (int) ($monthly[$m->format('Y-m')] ?? 0))],
            'daily' => ['labels' => $days->map->format('M d'), 'values' => $days->map(fn (Carbon $d) => (int) ($daily[$d->toDateString()] ?? 0))],
            'engagement' => $engagement,
        ]);
    }

    public function subscribers(Request $request): View
    {
        $lists = MailList::forWorkspace()->orderBy('name')->get(['id', 'uid', 'name']);
        $subscribers = Subscriber::with('list:id,uid,name')->whereIn('mail_list_id', $lists->pluck('id'))
            ->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.$request->q.'%'))
            ->when(in_array($request->status, Subscriber::STATUSES, true), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('list'), fn ($q) => $q->where('mail_list_id', $lists->firstWhere('uid', $request->list)?->id))
            ->latest('id')->paginate(50)->withQueryString();

        return view('audience.subscribers', compact('subscribers', 'lists'));
    }
}

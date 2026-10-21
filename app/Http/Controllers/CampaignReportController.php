<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CampaignReportController extends Controller
{
    public const LOGS = [
        'deliveries' => 'Delivery log',
        'opens' => 'Open log',
        'clicks' => 'Click log',
        'bounces' => 'Bounce log',
        'complaints' => 'Complaint log',
        'unsubscribes' => 'Unsubscribe log',
    ];

    public function log(Request $request, Campaign $campaign, string $log): View
    {
        if ($log === 'deliveries') {
            $rows = $campaign->deliveries()->with('sendingServer:id,name')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.$request->q.'%'))
                ->orderByDesc('id')->paginate(50)->withQueryString();
        } else {
            $type = ['opens' => 'open', 'clicks' => 'click', 'bounces' => 'bounce', 'complaints' => 'complaint', 'unsubscribes' => 'unsubscribe'][$log];
            $rows = $campaign->events()->with('delivery:id,email')->where('type', $type)
                ->when($request->filled('q'), fn ($q) => $q->whereHas('delivery', fn ($d) => $d->where('email', 'like', '%'.$request->q.'%')))
                ->orderByDesc('id')->paginate(50)->withQueryString();
        }

        return view('campaigns.log', ['campaign' => $campaign, 'log' => $log, 'rows' => $rows, 'logs' => self::LOGS]);
    }

    /** Opens & clicks per hour (first 3 days) or per day for the selected range. */
    public function chart(Request $request, Campaign $campaign): JsonResponse
    {
        $hours = (int) $request->query('hours', 72);
        $hourly = $hours <= 72;
        $from = ($campaign->started_at ?? $campaign->created_at)->copy()->startOfHour();
        $to = $from->copy()->addHours($hours);
        $format = $hourly ? '%Y-%m-%d %H:00' : '%Y-%m-%d';

        $rows = $campaign->events()->toBase()
            ->whereIn('type', ['open', 'click'])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("date_format(created_at, '{$format}') as bucket, type, count(*) as c")
            ->groupBy('bucket', 'type')->get()
            ->groupBy('type')->map(fn ($g) => $g->pluck('c', 'bucket'));

        $labels = [];
        for ($t = $from->copy(); $t < $to && $t <= now(); $hourly ? $t->addHour() : $t->addDay()) {
            $labels[] = $t->format($hourly ? 'Y-m-d H:00' : 'Y-m-d');
        }

        $tz = $campaign->workspace->timezone;

        return response()->json([
            'labels' => array_map(fn ($l) => Carbon::parse($l, 'UTC')->setTimezone($tz)->format($hourly ? 'M j H:00' : 'M j'), $labels),
            'opens' => array_map(fn ($l) => (int) ($rows['open'][$l] ?? 0), $labels),
            'clicks' => array_map(fn ($l) => (int) ($rows['click'][$l] ?? 0), $labels),
        ]);
    }
}

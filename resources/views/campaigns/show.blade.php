<x-layouts.app :title="$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')

    @if ($campaign->status === 'error' && $campaign->last_error)
        <div class="mb-5 flex gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><x-icon name="warning" class="size-5" /><span><strong>Sending stopped:</strong> {{ $campaign->last_error }}</span></div>
    @endif

    @if ($campaign->isRunning() || $campaign->status === 'paused')
        <div class="card card-body mb-6" x-data x-init="@if ($campaign->isRunning()) setTimeout(() => location.reload(), 10000) @endif">
            <div class="flex justify-between text-sm"><span class="font-medium">{{ $campaign->status === 'paused' ? 'Paused' : ($campaign->status === 'testing' ? 'A/B test running — the rest waits for the winner' : 'Sending in progress') }}</span><span class="tabular-nums text-gray-500">{{ number_format($stats['total'] - $stats['pending']) }} / {{ number_format($stats['total']) }} · {{ $stats['progress'] }}%</span></div>
            <div class="mt-2 h-2.5 rounded-full bg-gray-100"><div class="h-2.5 rounded-full bg-brand-600" style="width: {{ $stats['progress'] }}%"></div></div>
            @if ($campaign->sending_window)<p class="help">Sending only within the configured sending window ({{ $campaign->workspace->timezone }}).</p>@endif
        </div>
    @endif

    @if ($campaign->isAbTest() && $campaign->variants->isNotEmpty())
        @php $metric = $campaign->abSetting('metric') === 'clicks' ? 'click_rate' : 'open_rate'; $best = collect($variantStats)->max($metric); @endphp
        <div class="card mb-6">
            <div class="card-header">
                <div>
                    <h2 class="card-title">A/B test results</h2>
                    <p class="text-xs text-gray-500">
                        {{ $campaign->abSetting('test_percent') }}% test group · winner by {{ $campaign->abSetting('metric') }}
                        @if ($campaign->winner)
                            · <span class="font-medium text-emerald-700">Variant {{ $campaign->winner->label }} won</span>
                        @elseif ($campaign->test_ends_at)
                            · winner picked {{ $campaign->test_ends_at->isFuture() ? $campaign->test_ends_at->diffForHumans() : 'shortly' }}
                        @endif
                    </p>
                </div>
                @if (! $campaign->winner_variant_id && in_array($campaign->status, ['testing', 'paused']))
                    <form method="POST" action="{{ route('campaigns.winner', $campaign) }}" onsubmit="return confirm('End the test now and send the current leader to everyone else?')">@csrf
                        <button class="btn-secondary btn-sm">Pick winner now</button></form>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Variant</th><th>Changes</th><th>Sent</th><th>Open rate</th><th>Click rate</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($campaign->variants as $variant)
                            @php $vs = $variantStats[$variant->id]; @endphp
                            <tr>
                                <td class="font-semibold">{{ $variant->label }}
                                    @if ($campaign->winner_variant_id === $variant->id)<span class="badge-green ml-1">Winner</span>
                                    @elseif (! $campaign->winner_variant_id && $vs['sent'] && $vs[$metric] === $best)<span class="badge-blue ml-1">Leading</span>@endif</td>
                                <td class="max-w-sm truncate text-gray-600">{{ $variant->summary() }}</td>
                                <td class="tabular-nums">{{ number_format($vs['sent']) }}</td>
                                <td @class(['tabular-nums', 'font-semibold' => $metric === 'open_rate'])>{{ $vs['open_rate'] }}%</td>
                                <td @class(['tabular-nums', 'font-semibold' => $metric === 'click_rate'])>{{ $vs['click_rate'] }}%</td>
                                <td class="text-right">
                                    @if (! $campaign->winner_variant_id && in_array($campaign->status, ['testing', 'paused']))
                                        <form method="POST" action="{{ route('campaigns.winner', $campaign) }}" onsubmit="return confirm('Send variant {{ $variant->label }} to everyone else?')">@csrf
                                            <input type="hidden" name="variant" value="{{ $variant->id }}"><button class="btn-ghost btn-sm">Choose</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php
        $pctOf = fn ($n) => $stats['total'] ? round($n / $stats['total'] * 100, 1) : 0;
        $insight = match (true) {
            $stats['delivered'] === 0 => null,
            $stats['open_rate'] >= 30 => ['celebration', 'Great engagement', 'An open rate of '.$stats['open_rate'].'% is well above the typical 20–25%. Reuse what worked here in your next campaign.', ['border-emerald-200 bg-emerald-50', 'text-emerald-600', 'text-emerald-700']],
            $stats['open_rate'] >= 15 => ['thumb_up', 'Solid performance', 'An open rate of '.$stats['open_rate'].'% is in line with industry averages. A/B test subject lines to push it further.', ['border-blue-200 bg-blue-50', 'text-blue-600', 'text-blue-700']],
            default => ['lightbulb', 'Room to grow', 'Your open rate of '.$stats['open_rate'].'% has room for improvement. Try personalising subject lines or sending at different times.', ['border-amber-200 bg-amber-50', 'text-amber-600', 'text-amber-700']],
        };
    @endphp
    @if ($insight)
        <div class="mb-6 flex items-start gap-3 rounded-xl border px-5 py-4 {{ $insight[3][0] }}">
            <x-icon :name="$insight[0]" class="mt-0.5 size-5 {{ $insight[3][1] }}" />
            <div><p class="font-semibold {{ $insight[3][2] }}">{{ $insight[1] }}</p><p class="mt-0.5 text-sm text-[var(--muted)]">{{ $insight[2] }}</p></div>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        @foreach ([
            ['group', 'Recipients', $stats['total'], null, 'bg-blue-50 text-blue-600'],
            ['check_circle', 'Delivered', $stats['delivered'], $pctOf($stats['delivered']), 'bg-emerald-50 text-emerald-600'],
            ['error', 'Failed', $stats['failed'], $pctOf($stats['failed']), 'bg-red-50 text-red-600'],
            ['block', 'Skipped', $stats['skipped'], $pctOf($stats['skipped']), 'bg-gray-100 text-gray-500'],
            ['schedule_send', 'Pending', $stats['pending'], $pctOf($stats['pending']), 'bg-amber-50 text-amber-600'],
        ] as [$icon, $label, $value, $pct, $tile])
            <div class="card card-body !p-4">
                <div class="flex items-center justify-between"><span class="icon-tile size-9 {{ $tile }}"><x-icon :name="$icon" class="size-5" /></span>@if ($pct !== null)<span class="text-xs text-[var(--muted)] tabular-nums">{{ $pct }}%</span>@endif</div>
                <div class="stat-label mt-3">{{ $label }}</div>
                <div class="mt-1 text-[24px] leading-none font-bold tabular-nums">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Rate summary</h2></div>
            @foreach ([
                ['Opened', $stats['open_rate'], number_format($stats['opened']).' / '.number_format($stats['delivered']), 'bg-brand-500', 'opens'],
                ['Did not open', $stats['delivered'] ? round(100 - $stats['open_rate'], 2) : 0, number_format(max(0, $stats['delivered'] - $stats['opened'])).' recipients', 'bg-gray-400', null],
                ['Clicked', $stats['click_rate'], number_format($stats['clicked']).' / '.number_format($stats['delivered']), 'bg-blue-600', 'clicks'],
                ['Unsubscribed', $stats['unsubscribe_rate'], number_format($stats['unsubscribed']).' recipients', 'bg-amber-500', 'unsubscribes'],
                ['Bounced', $stats['bounce_rate'], 'Hard '.($bounceTypes['hard'] ?? 0).' · soft '.($bounceTypes['soft'] ?? 0), 'bg-red-500', 'bounces'],
                ['Reported as spam', $stats['complaint_rate'], number_format($stats['complained']).' reports', 'bg-red-500', 'complaints'],
            ] as [$label, $rate, $detail, $dot, $log])
                <a href="{{ $log ? route('campaigns.log', [$campaign, $log]) : '#' }}" class="flex items-center gap-3 border-b border-[var(--line)] px-6 py-3 text-sm last:border-0 hover:bg-[var(--surface-2)]">
                    <span class="size-2 rounded-full {{ $dot }}"></span>
                    <span class="flex-1">{{ $label }}</span>
                    <span class="font-semibold tabular-nums">{{ number_format($rate, 2) }}%</span>
                    <span class="w-24 text-right text-xs text-[var(--muted)]">{{ $detail }}</span>
                    @if ($log)<x-icon name="arrow_forward" class="size-4 text-[var(--muted)]" />@else<span class="w-4"></span>@endif
                </a>
            @endforeach
        </div>
        @foreach ([
            ['visibility', 'Open rate', $stats['open_rate'], 'opens', [['Successful deliveries', number_format($stats['delivered'])], ['Unique opens', number_format($stats['opened'])], ['Total opens', number_format($stats['total_opens'])], ['Click-to-open rate', $stats['ctor'].'%']]],
            ['ads_click', 'Click rate', $stats['click_rate'], 'clicks', [['Total clicks', number_format($stats['total_clicks'])], ['Unique clicks', number_format($stats['clicked'])], ['Abuse reports', number_format($stats['complained'])], ['Started', $campaign->started_at?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—']]],
        ] as [$icon, $label, $rate, $log, $rows])
            <div class="card">
                <div class="card-header"><div class="flex items-center gap-2"><x-icon :name="$icon" class="size-5 text-[var(--muted)]" /><h2 class="card-title">{{ $label }}</h2></div>
                    <a href="{{ route('campaigns.log', [$campaign, $log]) }}" class="flex items-center gap-1 text-xs text-brand-600 hover:underline"><x-icon name="open_in_new" class="size-3.5" /> {{ ucfirst(rtrim($log, 's')) }} log</a></div>
                <div class="card-body">
                    <div class="text-[32px] leading-none font-bold tabular-nums">{{ number_format($rate, 2) }}%</div>
                    <div class="mt-3 h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ min(100, $rate) }}%"></div></div>
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ($rows as [$k, $v])<div class="flex justify-between"><dt class="text-[var(--muted)]">{{ $k }}</dt><dd class="font-medium tabular-nums">{{ $v }}</dd></div>@endforeach
                    </dl>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Opens & clicks over time</h2>
                <select id="range" class="input w-auto py-1 text-xs"><option value="24">24 hours</option><option value="72" selected>3 days</option><option value="168">7 days</option><option value="720">30 days</option></select></div>
            <div class="card-body"><canvas id="timeline" height="120"></canvas></div>
        </div>
        <div class="card card-body space-y-2 text-sm">
            <h2 class="card-title mb-2">Campaign details</h2>
            <div class="flex justify-between gap-3"><span class="text-gray-500">From</span><span class="truncate text-right">{{ $campaign->from_name }} &lt;{{ $campaign->from_email }}&gt;</span></div>
            <div class="flex justify-between gap-3"><span class="text-gray-500">Reply-to</span><span class="truncate">{{ $campaign->reply_to ?: '—' }}</span></div>
            <div><span class="text-gray-500">Audience</span>
                <ul class="mt-1 space-y-0.5">@foreach ($campaign->audiences as $a)<li class="truncate">{{ $a->exclude ? 'Excluding ' : '' }}{{ $a->list->name }}{{ $a->segment ? ' › '.$a->segment->name : '' }}</li>@endforeach</ul></div>
            <div class="flex justify-between gap-3"><span class="text-gray-500">Tracking</span><span>{{ collect(['Opens' => $campaign->track_open, 'Clicks' => $campaign->track_click])->filter()->keys()->implode(', ') ?: 'Off' }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-gray-500">DKIM signing</span><span>{{ $campaign->sign_dkim ? 'On' : 'Off' }}</span></div>
        </div>
    </div>

    @if (collect($breakdowns)->flatten()->isNotEmpty())
        <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($breakdowns as $title => $rows)
                <div class="card card-body">
                    <h2 class="card-title mb-3">{{ $title }}</h2>
                    @php $max = max(1, $rows->max() ?? 1); $labels = ['proxy' => 'Hidden by mail proxy', 'desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet']; @endphp
                    @forelse ($rows as $label => $readers)
                        <div class="mb-2">
                            <div class="flex justify-between text-sm"><span class="truncate text-gray-700">{{ $title === 'Country' ? config('countries.'.$label, $label) : ($labels[$label] ?? $label) }}</span><span class="tabular-nums text-gray-500">{{ number_format($readers) }}</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($readers / $max * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">{{ $title === 'Country' ? 'Needs a CDN country header (e.g. Cloudflare).' : 'No data yet.' }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>
        <p class="help mt-2">Gmail, Yahoo and Apple Mail Privacy Protection load images through proxies, which hide the reader's device and location.</p>
    @endif

    <div class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">Top links</h2><a href="{{ route('campaigns.log', [$campaign, 'clicks']) }}" class="text-sm text-brand-600 hover:underline">Click log &rarr;</a></div>
        @if ($topLinks->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-500">No clicks yet.</p>
        @else
            <table class="table"><thead><tr><th>URL</th><th>Clicks</th><th>Unique</th></tr></thead><tbody>
                @foreach ($topLinks as $link)<tr><td class="max-w-xl truncate"><a href="{{ $link->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $link->url }}</a></td><td>{{ $link->clicks }}</td><td>{{ $link->unique_clicks }}</td></tr>@endforeach
            </tbody></table>
        @endif
    </div>

    @push('scripts')
        <script type="module">
            const ctx = document.getElementById('timeline');
            const chart = new Chart(ctx, { type: 'line', data: { labels: [], datasets: [
                { label: 'Opens', data: [], borderColor: themeColor('--color-brand-500'), backgroundColor: 'rgba(74,222,128,0.08)', fill: true, tension: 0.3, pointRadius: 3 },
                { label: 'Clicks', data: [], borderColor: '#60a5fa', tension: 0.3, pointRadius: 3 },
            ] }, options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 8 } } } } });
            async function load(hours) {
                const d = await (await fetch(@js(route('campaigns.chart', $campaign)) + '?hours=' + hours)).json();
                chart.data.labels = d.labels; chart.data.datasets[0].data = d.opens; chart.data.datasets[1].data = d.clicks; chart.update();
            }
            document.getElementById('range').addEventListener('change', e => load(e.target.value));
            load(72);
        </script>
    @endpush
</x-layouts.app>

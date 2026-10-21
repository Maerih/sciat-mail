<x-layouts.app :title="$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')

    @if ($campaign->status === 'error' && $campaign->last_error)
        <div class="mb-5 flex gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><x-icon name="warning" class="size-5" /><span><strong>Sending stopped:</strong> {{ $campaign->last_error }}</span></div>
    @endif

    @if ($campaign->isRunning() || $campaign->status === 'paused')
        <div class="card card-body mb-6" x-data x-init="@if ($campaign->isRunning()) setTimeout(() => location.reload(), 10000) @endif">
            <div class="flex justify-between text-sm"><span class="font-medium">{{ $campaign->status === 'paused' ? 'Paused' : 'Sending in progress' }}</span><span class="tabular-nums text-gray-500">{{ number_format($stats['total'] - $stats['pending']) }} / {{ number_format($stats['total']) }} · {{ $stats['progress'] }}%</span></div>
            <div class="mt-2 h-2.5 rounded-full bg-gray-100"><div class="h-2.5 rounded-full bg-brand-600" style="width: {{ $stats['progress'] }}%"></div></div>
            @if ($campaign->sending_window)<p class="help">Sending only within the configured sending window ({{ $campaign->workspace->timezone }}).</p>@endif
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Delivered" :value="number_format($stats['delivered'])" :hint="$stats['failed'] ? number_format($stats['failed']).' failed · '.number_format($stats['skipped']).' skipped' : number_format($stats['total']).' recipients'" />
        <x-stat label="Open rate" :value="$stats['open_rate'].'%'" :hint="number_format($stats['opened']).' unique · '.number_format($stats['total_opens']).' total'" tone="green" />
        <x-stat label="Click rate" :value="$stats['click_rate'].'%'" :hint="number_format($stats['clicked']).' unique · CTOR '.$stats['ctor'].'%'" tone="green" />
        <x-stat label="Unsubscribed" :value="$stats['unsubscribe_rate'].'%'" :hint="number_format($stats['unsubscribed']).' people'" />
    </div>
    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Bounce rate" :value="$stats['bounce_rate'].'%'" :hint="'Hard '.($bounceTypes['hard'] ?? 0).' · soft '.($bounceTypes['soft'] ?? 0)" :tone="$stats['bounce_rate'] > 2 ? 'red' : 'gray'" />
        <x-stat label="Spam complaints" :value="$stats['complaint_rate'].'%'" :hint="number_format($stats['complained']).' reports'" :tone="$stats['complaint_rate'] > 0.1 ? 'red' : 'gray'" />
        <x-stat label="Started" :value="$campaign->started_at?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—'" />
        <x-stat label="Finished" :value="$campaign->finished_at?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—'" />
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
                { label: 'Opens', data: [], borderColor: 'oklch(0.58 0.19 265)', backgroundColor: 'oklch(0.58 0.19 265 / 0.1)', fill: true, tension: 0.3, pointRadius: 3 },
                { label: 'Clicks', data: [], borderColor: 'oklch(0.7 0.15 160)', tension: 0.3, pointRadius: 3 },
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

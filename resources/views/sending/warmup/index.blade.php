<x-layouts.app title="IP warmup">
    <x-page-header title="IP warmup" subtitle="Ramp up sending volume on new IPs and domains so mailbox providers learn to trust them.">
        <x-slot:actions><a href="{{ route('sending.warmup.create') }}" class="btn-primary"><x-icon name="plus" /> New strategy</a></x-slot:actions>
    </x-page-header>
    <x-banner icon="local_fire_department" title="Warm up new IPs and domains" text="New senders have no reputation. A warmup plan raises the daily volume gradually so Gmail, Outlook and Yahoo learn to trust your servers."
        :stats="[['tune', $strategies->count().' strategies'], ['dns', $servers->filter->isWarmingUp()->count().' servers warming up']]" />

    @if ($servers->isNotEmpty())
        <div class="card mb-6 overflow-x-auto">
            <div class="card-header"><h2 class="card-title">Servers with warmup</h2></div>
            <table class="table">
                <thead><tr><th>Server</th><th>Strategy</th><th>Progress</th><th>Today's quota</th></tr></thead>
                <tbody>
                    @foreach ($servers as $server)
                        @php $day = $server->warmupDay(); $days = $server->warmupStrategy->days; @endphp
                        <tr>
                            <td><a href="{{ route('sending.servers.edit', $server) }}" class="font-medium hover:text-brand-600">{{ $server->name }}</a></td>
                            <td>{{ $server->warmupStrategy->name }}</td>
                            <td class="w-64">
                                @if ($day < 1)
                                    <span class="text-gray-500">Starts {{ $server->warmup_started_on->toFormattedDateString() }}</span>
                                @elseif ($server->isWarmingUp())
                                    <div class="text-xs text-gray-500">Day {{ $day }} of {{ $days }}</div>
                                    <div class="mt-1 h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-amber-500" style="width: {{ round($day / $days * 100) }}%"></div></div>
                                @else
                                    <span class="badge-green">Warmed up</span>
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $server->effectiveDailyLimit() ? number_format($server->effectiveDailyLimit()) : 'No limit' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($strategies as $strategy)
            <div class="card card-body">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ $strategy->name }}</h2>
                        <p class="text-xs text-gray-500">{{ ucfirst($strategy->curve) }} · {{ $strategy->days }} days · {{ $strategy->isPreset() ? 'Built-in' : 'Custom' }} · used by {{ $strategy->servers_count }} server(s)</p>
                    </div>
                    @unless ($strategy->isPreset())
                        <span class="flex gap-1">
                            <a href="{{ route('sending.warmup.edit', $strategy) }}" class="btn-ghost btn-sm">Edit</a>
                            <x-confirm-delete :action="route('sending.warmup.destroy', $strategy)" label="" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></x-confirm-delete>
                        </span>
                    @endunless
                </div>
                <canvas class="mt-3" height="120" data-schedule='@json(array_values($strategy->schedule()))'></canvas>
                <p class="mt-2 text-xs text-gray-500">{{ number_format($strategy->initial_daily) }} → {{ number_format($strategy->target_daily) }} emails per day</p>
            </div>
        @endforeach
    </div>

    <div class="card card-body mt-6 text-sm text-gray-600">
        <h2 class="card-title mb-2">How warmup works</h2>
        <ul class="list-inside list-disc space-y-1">
            <li>Attach a strategy to a sending server (Sending servers → Configure → IP warmup).</li>
            <li>Each day the server may send only that day's quota; extra emails wait and continue the next day.</li>
            <li>Send to your most engaged contacts first during warmup and watch bounce and complaint rates.</li>
            <li>After the last day the target volume applies — or your own daily limit, whichever is lower.</li>
        </ul>
    </div>

    @push('scripts')
        <script type="module">
            document.querySelectorAll('canvas[data-schedule]').forEach(el => {
                const data = JSON.parse(el.dataset.schedule);
                new Chart(el, { type: 'bar', data: { labels: data.map((_, i) => 'Day ' + (i + 1)), datasets: [{ data, backgroundColor: '#fbbf24', borderRadius: 2 }] },
                    options: { plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { beginAtZero: true, ticks: { maxTicksLimit: 4 } } } } });
            });
        </script>
    @endpush
</x-layouts.app>

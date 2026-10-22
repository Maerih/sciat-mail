<x-layouts.app title="Sending servers">
    <x-page-header title="Sending servers" subtitle="The services that actually deliver this client's email. Shared servers are available to every client.">
        <x-slot:actions><a href="{{ route('sending.servers.select') }}" class="btn-primary"><x-icon name="plus" /> Add server</a></x-slot:actions>
    </x-page-header>
    @php
        $active = $servers->filter->isActive();
        $hourly = $active->contains(fn ($server) => ! $server->hourly_limit) ? null : $active->sum('hourly_limit');
        $mix = $servers->groupBy(fn ($server) => $server->typeLabel())->map->count();
        $warming = $servers->filter->isWarmingUp();
    @endphp
    <x-banner icon="dns" title="Power your email delivery" text="Sending servers handle the actual delivery of your campaigns. Connect SMTP relays or Amazon SES, SendGrid and Mailgun to ensure every message lands in the inbox."
        :stats="[['dns', $servers->count().' connected'], ['check_circle', $active->count().' active'], ['speed', ($hourly === null ? 'Unlimited' : number_format($hourly).'/hour').' combined throughput'], ['category', $mix->count().' '.Str::plural('vendor', $mix->count())]]" />
    <div class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <div class="card min-w-0">
        @if ($servers->isEmpty())
            <x-empty title="No sending servers" icon="server">Connect SMTP, Amazon SES, SendGrid or Mailgun to start sending.
                <x-slot:action><a href="{{ route('sending.servers.select') }}" class="btn-primary">Add server</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Server</th><th>Type</th><th>Status</th><th>Limits</th><th>Last test</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($servers as $server)
                            <tr>
                                <td><a href="{{ route('sending.servers.edit', $server) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $server->name }}</a>
                                    @if ($server->isShared())<span class="badge-purple ml-1">Shared</span>@endif</td>
                                <td>{{ $server->typeLabel() }}</td>
                                <td><x-status-badge :status="$server->status" /></td>
                                <td class="text-gray-500">{{ $server->hourly_limit ? number_format($server->hourly_limit).'/h' : '∞/h' }} · {{ $server->effectiveDailyLimit() ? number_format($server->effectiveDailyLimit()).'/day' : '∞/day' }}
                                    @if ($server->prewarmed)<span class="badge-green ml-1">Pre-warmed</span>@endif
                                    @if ($server->isWarmingUp())<span class="badge-yellow ml-1">Warmup day {{ $server->warmupDay() }}/{{ $server->warmupStrategy->days }}</span>@endif</td>
                                <td>@if ($server->last_tested_at)<span @class(['text-red-600' => $server->last_error, 'text-emerald-600' => ! $server->last_error])>{{ $server->last_error ? 'Failed' : 'OK' }}</span> <span class="text-xs text-gray-400">{{ $server->last_tested_at->diffForHumans() }}</span>@else<span class="text-gray-400">Never</span>@endif</td>
                                <td class="text-right"><a href="{{ route('sending.servers.edit', $server) }}" class="btn-ghost btn-sm">Configure</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="donut_large" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Sender mix</h2></div></div>
            <div class="card-body">
                @if ($servers->isEmpty())
                    <p class="text-sm text-[var(--muted)]">No servers yet.</p>
                @else
                    <div class="relative mx-auto size-40"><canvas id="sender-mix"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"><span class="text-2xl font-bold">{{ $servers->count() }}</span><span class="text-xs text-[var(--muted)]">{{ Str::plural('server', $servers->count()) }}</span></div>
                    </div>
                    <ul class="mt-4 space-y-1.5 text-sm">
                        @foreach ($mix as $type => $count)
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full" data-mix-dot="{{ $loop->index }}"></span>{{ $type }}</span><span class="tabular-nums text-[var(--muted)]">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="card card-body">
            <div class="flex items-center gap-2 text-sm font-semibold"><x-icon name="speed" class="size-5 text-[var(--muted)]" /> Combined throughput</div>
            <div class="mt-3 text-[28px] leading-none font-bold tabular-nums">{{ $hourly === null ? '∞' : number_format($hourly) }} <span class="text-sm font-normal text-[var(--muted)]">/ hour</span></div>
            <p class="mt-2 text-xs text-[var(--muted)]">Sum of the hourly limits of active servers.</p>
        </div>
        <div class="card card-body">
            <div class="flex items-center gap-2 text-sm font-semibold"><x-icon name="local_fire_department" class="size-5 text-[var(--muted)]" /> Warmup status</div>
            @forelse ($warming as $server)
                <div class="mt-3">
                    <div class="flex justify-between text-sm"><span>{{ $server->name }}</span><span class="text-[var(--muted)]">Day {{ $server->warmupDay() }}/{{ $server->warmupStrategy->days }}</span></div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ min(100, round($server->warmupDay() / max(1, $server->warmupStrategy->days) * 100)) }}%"></div></div>
                </div>
            @empty
                <p class="mt-2 text-xs text-[var(--muted)]">No servers are warming up. New IPs should ramp up gradually — <a href="{{ route('sending.warmup.index') }}" class="text-brand-600 hover:underline">set up a warmup strategy</a>.</p>
            @endforelse
        </div>
    </div>
    </div>

    @if ($servers->isNotEmpty())
        @push('scripts')
            <script type="module">
                const palette = [themeColor('--color-brand-500'), '#60a5fa', '#a78bfa', '#fbbf24', '#f87171'];
                document.querySelectorAll('[data-mix-dot]').forEach(el => el.style.background = palette[el.dataset.mixDot % palette.length]);
                new Chart(document.getElementById('sender-mix'), {
                    type: 'doughnut',
                    data: { labels: @js($mix->keys()), datasets: [{ data: @js($mix->values()), backgroundColor: palette, borderWidth: 0 }] },
                    options: { cutout: '78%', plugins: { legend: { display: false } } },
                });
            </script>
        @endpush
    @endif
</x-layouts.app>

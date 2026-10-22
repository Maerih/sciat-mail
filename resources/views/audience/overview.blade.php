<x-layouts.app title="Audience">
    <x-page-header title="Audience" subtitle="Aggregate view of all your contact lists and subscribers.">
        <x-slot:actions><a href="{{ route('lists.create') }}" class="btn-primary"><x-icon name="add" /> Create list</a></x-slot:actions>
    </x-page-header>

    @php
        $statusMeta = ['subscribed' => ['Subscribed', 'bg-brand-500'], 'unsubscribed' => ['Unsubscribed', 'bg-red-500'], 'unconfirmed' => ['Unconfirmed', 'bg-amber-500'], 'blacklisted' => ['Blacklisted', 'bg-gray-400']];
    @endphp
    <div class="card relative mb-6 overflow-hidden">
        <div class="pointer-events-none absolute inset-y-0 left-0 w-2/3 bg-gradient-to-r from-brand-500/10 to-transparent"></div>
        <div class="relative grid gap-8 p-7 lg:grid-cols-[1fr_360px]">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-50 px-2 py-1 text-[11px] font-semibold tracking-wider text-brand-600 uppercase"><x-icon name="auto_awesome" class="size-3.5" /> Grow faster</span>
                <h2 class="mt-3 text-[28px] leading-tight font-bold tracking-tight">Supercharge your audience growth</h2>
                <p class="mt-2 max-w-xl text-sm text-[var(--muted)]">Every subscriber is a potential customer. Grow your lists faster, keep contacts engaged and reach the right people at the right time.</p>
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach (['Embed signup forms on any page — grow on autopilot', 'Import existing contacts from CSV in seconds', 'Segment by behaviour, tags or custom fields', 'Automate welcome emails for every new subscriber'] as $point)
                        <li class="flex items-center gap-2"><x-icon name="check_circle" class="size-4 text-brand-600" /> {{ $point }}</li>
                    @endforeach
                </ul>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('lists.create') }}" class="btn-primary">Create a list</a>
                    <a href="{{ route('lists.index') }}" class="btn-secondary">Browse lists</a>
                </div>
            </div>
            <div class="rounded-xl border border-[var(--line)] bg-[var(--page)] p-5">
                <h3 class="text-sm font-semibold">Audience health</h3>
                <div class="mt-4 space-y-3.5">
                    @foreach ($statusMeta as $status => [$label, $color])
                        <div>
                            <div class="flex justify-between text-xs"><span class="text-[var(--muted)]">{{ $label }}</span><span class="font-semibold tabular-nums">{{ number_format($counts[$status]) }}</span></div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full {{ $color }}" style="width: {{ $total ? round($counts[$status] / $total * 100, 1) : 0 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-between border-t border-[var(--line)] pt-3 text-sm"><span class="text-[var(--muted)]">Total</span><span class="font-bold text-brand-600 tabular-nums">{{ number_format($total) }}</span></div>
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total subscribers" :value="number_format($total)" :hint="'Across '.$lists->count().' '.Str::plural('list', $lists->count())" icon="group" tone="green" />
        <x-stat label="Active lists" :value="number_format($lists->count())" icon="format_list_bulleted" hint="Manage lists →" />
        <x-stat label="Unconfirmed" :value="number_format($counts['unconfirmed'])" hint="Awaiting confirmation" icon="pending" tone="amber" />
        <x-stat label="Unsubscribed" :value="number_format($counts['unsubscribed'])" hint="Opted out" icon="person_remove" tone="red" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Subscriber growth</h2><p class="text-xs text-[var(--muted)]">New subscribers added each month across all lists</p></div></div>
            <div class="card-body"><div class="h-60"><canvas id="growth"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Subscriber breakdown</h2><p class="text-xs text-[var(--muted)]">Status composition across all lists</p></div></div>
            <div class="card-body">
                @if ($total)
                    <div class="mx-auto size-44"><canvas id="breakdown"></canvas></div>
                    <ul class="mt-4 grid grid-cols-2 gap-1.5 text-xs">
                        @foreach ($statusMeta as $status => [$label, $color])<li class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $color }}"></span>{{ $label }}</li>@endforeach
                    </ul>
                @else
                    <p class="py-10 text-center text-sm text-[var(--muted)]">No subscribers yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Daily signups — last 30 days</h2><p class="text-xs text-[var(--muted)]">New subscribers per day across all lists</p></div></div>
            <div class="card-body"><div class="h-52"><canvas id="daily"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="bolt" class="size-5 text-brand-600" /><h2 class="card-title">Quick actions</h2></div></div>
            @foreach ([
                ['add', 'Create list', 'Set up a new contact list', route('lists.create'), 'Create'],
                ['upload', 'Import contacts', 'Upload a CSV to your top list', $lists->first() ? route('lists.import.create', $lists->first()) : route('lists.create'), 'Import'],
                ['edit_note', 'Signup form', 'Collect subscribers on your website', route('forms.create'), 'Create'],
                ['format_list_bulleted', 'All lists', 'Manage lists and subscribers', route('lists.index'), 'View'],
            ] as [$icon, $label, $text, $url, $cta])
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-[var(--line)] px-6 py-3 last:border-0 hover:bg-[var(--surface-2)]">
                    <span class="icon-tile size-9 bg-[var(--surface-2)] text-[var(--muted)]"><x-icon :name="$icon" class="size-5" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium">{{ $label }}</span><span class="block text-xs text-[var(--muted)]">{{ $text }}</span></span>
                    <span class="text-xs font-medium text-brand-600">{{ $cta }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">All lists</h2><a href="{{ route('lists.index') }}" class="text-sm text-brand-600 hover:underline">Manage →</a></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>List name</th><th class="text-right">Subscribers</th><th class="text-right">Subscribed</th><th class="text-right">Open rate</th><th class="text-right">Click rate</th><th></th></tr></thead>
                <tbody>
                    @forelse ($lists as $list)
                        @php $e = $engagement[$list->id] ?? null; @endphp
                        <tr>
                            <td><a href="{{ route('lists.show', $list) }}" class="font-medium hover:text-brand-600">{{ $list->name }}</a></td>
                            <td class="text-right tabular-nums">{{ number_format($list->subscribers_count) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($list->subscribed_count) }}</td>
                            <td class="text-right tabular-nums">{{ $e && $e->sent ? round($e->opened / $e->sent * 100) : 0 }}%</td>
                            <td class="text-right tabular-nums">{{ $e && $e->sent ? round($e->clicked / $e->sent * 100) : 0 }}%</td>
                            <td class="text-right"><a href="{{ route('lists.show', $list) }}" class="text-sm text-brand-600 hover:underline">View →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-[var(--muted)]">No lists yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const brand = themeColor('--color-brand-500');
            const growth = @js($growth), daily = @js($daily);
            new Chart(document.getElementById('growth'), {
                type: 'bar',
                data: { labels: growth.labels, datasets: [{ label: 'New subscribers', data: growth.values, backgroundColor: brand, borderRadius: 6, maxBarThickness: 40 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
            });
            new Chart(document.getElementById('daily'), {
                type: 'line',
                data: { labels: daily.labels, datasets: [{ label: 'Signups', data: daily.values, borderColor: brand, backgroundColor: brand + '22', fill: true, tension: 0.35, pointRadius: 0 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 6 } } } },
            });
            const breakdown = document.getElementById('breakdown');
            if (breakdown) {
                new Chart(breakdown, {
                    type: 'doughnut',
                    data: { labels: ['Subscribed', 'Unsubscribed', 'Unconfirmed', 'Blacklisted'], datasets: [{ data: @js(array_values($counts->only(['subscribed', 'unsubscribed', 'unconfirmed', 'blacklisted'])->all())), backgroundColor: [brand, '#f87171', '#fbbf24', '#8b8b93'], borderWidth: 0 }] },
                    options: { cutout: '72%', plugins: { legend: { display: false } } },
                });
            }
        </script>
    @endpush
</x-layouts.app>

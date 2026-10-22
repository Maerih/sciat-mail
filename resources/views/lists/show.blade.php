<x-layouts.app :title="$list->name">
    <x-page-header :title="$list->name" :subtitle="number_format($total).' contacts'" :back="route('lists.index')">
        <x-slot:actions>
            <a href="{{ route('lists.subscribers.create', $list) }}" class="btn-primary"><x-icon name="person_add" class="size-4.5" /> Add subscriber</a>
        </x-slot:actions>
    </x-page-header>
    @include('lists._tabs')

    @php
        $pct = fn ($n) => $total ? round($n / $total * 100, 1) : 0;
        $steps = [
            ['List created', true, null],
            ['Add your contacts', $total > 0, route('lists.import.create', $list)],
            ['Create a segment', $segmentCount > 0, route('lists.segments.create', $list)],
            ['Set up a signup form', $formCount > 0, route('forms.create', ['list' => $list->uid])],
        ];
        $done = collect($steps)->where(1, true)->count();
    @endphp

    <div class="card relative mb-6 overflow-hidden">
        <div class="pointer-events-none absolute inset-y-0 left-0 w-1/2 bg-gradient-to-r from-brand-500/10 to-transparent"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-4 px-7 py-6">
            <div>
                <h2 class="flex items-center gap-2 text-[24px] font-bold tracking-tight"><x-icon name="trending_up" class="size-7 text-brand-600" /> {{ number_format($counts['subscribed']) }} subscribers</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">{{ $counts['subscribed'] ? 'Growing audience — keep building momentum.' : 'Your list is ready — add your first contacts.' }} Open rate {{ $openRate }}% · Click rate {{ $clickRate }}%.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('lists.import.create', $list) }}" class="btn-secondary"><x-icon name="upload_file" class="size-4.5" /> Import</a>
                <a href="{{ route('forms.create', ['list' => $list->uid]) }}" class="btn-secondary"><x-icon name="code" class="size-4.5" /> Signup form</a>
            </div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['visibility', 'Avg. open rate', $openRate.'%', null, $openRate, 'bg-brand-500'],
            ['ads_click', 'Click rate', $clickRate.'%', null, $clickRate, 'bg-blue-600'],
            ['person_add', 'Subscribe rate', $pct($counts['subscribed']).'%', number_format($counts['subscribed']).' subscribed', $pct($counts['subscribed']), 'bg-brand-500'],
            ['person_remove', 'Unsub rate', $pct($counts['unsubscribed']).'%', number_format($counts['unsubscribed']).' unsubscribed', $pct($counts['unsubscribed']), 'bg-red-500'],
            ['pending', 'Unconfirmed', number_format($counts['unconfirmed']), 'Awaiting confirmation', $pct($counts['unconfirmed']), 'bg-amber-500'],
            ['block', 'Blacklisted', number_format($counts['blacklisted']), 'Blocked from receiving', $pct($counts['blacklisted']), 'bg-gray-400'],
        ] as [$icon, $label, $value, $hint, $bar, $color])
            <div class="card card-body !p-4">
                <div class="flex items-center gap-1.5 text-xs text-[var(--muted)]"><x-icon :name="$icon" class="size-4" /> {{ $label }}</div>
                <div class="mt-2 text-[24px] leading-none font-bold tabular-nums">{{ $value }}</div>
                @if ($hint)<div class="mt-1 text-xs text-[var(--muted)]">{{ $hint }}</div>@endif
                <div class="mt-3 h-1 rounded-full bg-[var(--surface-2)]"><div class="h-1 rounded-full {{ $color }}" style="width: {{ min(100, $bar) }}%"></div></div>
            </div>
        @endforeach
    </div>

    @if ($done < count($steps))
        <div class="card mb-6">
            <div class="border-b border-[var(--line)] px-6 py-4">
                <div class="flex items-center justify-between text-sm"><span class="font-semibold">You're {{ round($done / count($steps) * 100) }}% closer to best-in-class email marketing</span><span class="text-[var(--muted)]">{{ $done }}/{{ count($steps) }} complete</span></div>
                <div class="mt-2 h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $done / count($steps) * 100 }}%"></div></div>
            </div>
            <div class="grid md:grid-cols-[280px_1fr]">
                <ul class="space-y-1 border-b border-[var(--line)] p-4 md:border-r md:border-b-0">
                    @foreach ($steps as [$label, $complete, $url])
                        <li>
                            <a href="{{ $url ?? '#' }}" @class(['flex items-center gap-2.5 rounded-lg px-2 py-2 text-sm', 'hover:bg-[var(--surface-2)]' => $url, 'text-[var(--muted)]' => $complete])>
                                <span @class(['flex size-5 items-center justify-center rounded-full', 'bg-brand-500 text-[var(--on-brand)]' => $complete, 'border border-[var(--line)]' => ! $complete])>@if ($complete)<x-icon name="check" class="size-3.5" />@endif</span>
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @php [$nextLabel, , $nextUrl] = collect($steps)->firstWhere(1, false); @endphp
                <div class="flex flex-col justify-center p-6">
                    <h3 class="text-lg font-semibold">{{ ['Add your contacts' => 'Bring your contacts in', 'Create a segment' => 'Target the right people', 'Set up a signup form' => 'Grow your list on autopilot'][$nextLabel] }}</h3>
                    <p class="mt-1 max-w-lg text-sm text-[var(--muted)]">{{ [
                        'Add your contacts' => 'Upload a CSV or add subscribers one by one. Fields and tags are mapped automatically.',
                        'Create a segment' => 'Group contacts by fields, tags, engagement or verification status to send more relevant campaigns.',
                        'Set up a signup form' => 'Embed a signup form on your website. Every visitor who signs up goes straight into this list — no manual work needed.',
                    ][$nextLabel] }}</p>
                    <div class="mt-4"><a href="{{ $nextUrl }}" class="btn-primary btn-sm">{{ $nextLabel }}</a></div>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Subscriber composition</h2><p class="text-xs text-[var(--muted)]">Current composition by status</p></div></div>
            <div class="card-body">
                @if ($total)
                    <div class="mx-auto size-40"><canvas id="composition"></canvas></div>
                    <ul class="mt-4 grid grid-cols-2 gap-1.5 text-xs">
                        @foreach (['Subscribed' => 'bg-brand-500', 'Unsubscribed' => 'bg-red-500', 'Unconfirmed' => 'bg-amber-500', 'Blacklisted' => 'bg-gray-400'] as $label => $color)<li class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $color }}"></span>{{ $label }}</li>@endforeach
                    </ul>
                @else
                    <p class="py-10 text-center text-sm text-[var(--muted)]">No subscribers yet.</p>
                @endif
            </div>
        </div>
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Monthly subscriber growth</h2><p class="text-xs text-[var(--muted)]">New subscribers added each month</p></div></div>
            <div class="card-body"><div class="h-56"><canvas id="monthly"></canvas></div></div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Daily signups — last 30 days</h2><p class="text-xs text-[var(--muted)]">New subscribers per day</p></div></div>
            <div class="card-body"><div class="h-52"><canvas id="growth"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="bolt" class="size-5 text-brand-600" /><h2 class="card-title">Quick actions</h2></div></div>
            @foreach ([
                ['upload_file', 'Import contacts', 'Upload a CSV to add many subscribers at once', route('lists.import.create', $list), 'Import'],
                ['person_add', 'Add subscriber', 'Manually add a single contact to this list', route('lists.subscribers.create', $list), 'Add'],
                ['splitscreen', 'Create a segment', 'Group contacts by shared attributes', route('lists.segments.create', $list), 'Create'],
            ] as [$icon, $label, $text, $url, $cta])
                <a href="{{ $url }}" class="flex items-center gap-3 border-b border-[var(--line)] px-6 py-3 last:border-0 hover:bg-[var(--surface-2)]">
                    <span class="icon-tile size-9 bg-[var(--surface-2)] text-[var(--muted)]"><x-icon :name="$icon" class="size-5" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium">{{ $label }}</span><span class="block text-xs text-[var(--muted)]">{{ $text }}</span></span>
                    <span class="text-xs font-medium text-brand-600">{{ $cta }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Recent subscribers</h2><a href="{{ route('lists.subscribers.index', $list) }}" class="text-sm text-brand-600 hover:underline">View all →</a></div>
            <table class="table">
                <thead><tr><th>Email</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                    @forelse ($recent as $subscriber)
                        <tr>
                            <td><a href="{{ route('lists.subscribers.show', [$list, $subscriber]) }}" class="flex items-center gap-3 font-medium hover:text-brand-600">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 uppercase">{{ substr($subscriber->email, 0, 1) }}</span>{{ $subscriber->email }}</a></td>
                            <td><x-status-badge :status="$subscriber->status" /></td>
                            <td class="text-[var(--muted)]">{{ $subscriber->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-[var(--muted)]">No subscribers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card card-body space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="card-title">Email verification</h2>
                @if (in_array($list->verification_job_status, ['queued', 'running']))
                    <span class="badge-blue">{{ ucfirst($list->verification_job_status) }}…</span>
                @else
                    <form method="POST" action="{{ route('lists.verify', $list) }}">@csrf<button class="btn-secondary btn-sm"><x-icon name="mark_email_read" class="size-4" /> Verify list</button></form>
                @endif
            </div>
            <div>
                @foreach (\App\Models\VerificationServer::STATUSES as $v)
                    <a href="{{ route('lists.subscribers.index', [$list, 'verification' => $v]) }}" class="flex justify-between py-1 text-sm text-[var(--muted)] hover:text-[var(--text)]"><span>{{ ucfirst($v) }}</span><span class="tabular-nums">{{ number_format($verification[$v] ?? 0) }}</span></a>
                @endforeach
                <p class="help">Undeliverable addresses are skipped by every campaign.</p>
            </div>
            <div class="border-t border-[var(--line)] pt-4 text-sm text-[var(--muted)]">
                <div class="flex justify-between"><span>Double opt-in</span><span class="text-[var(--text)]">{{ $list->double_optin ? 'On' : 'Off' }}</span></div>
                <div class="mt-1.5 flex justify-between"><span>Welcome email</span><span class="text-[var(--text)]">{{ $list->send_welcome_email ? 'On' : 'Off' }}</span></div>
                <div class="mt-1.5 flex justify-between gap-3"><span>Sender</span><span class="truncate text-[var(--text)]">{{ $list->from_email }}</span></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const brand = themeColor('--color-brand-500');
            const days = @js($days), months = @js($months);
            new Chart(document.getElementById('growth'), {
                type: 'line',
                data: { labels: Object.keys(days).map(d => d.slice(5)), datasets: [{ label: 'New subscribers', data: Object.values(days), borderColor: brand, backgroundColor: brand + '22', fill: true, tension: 0.35, pointRadius: 0 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 6 } } } },
            });
            new Chart(document.getElementById('monthly'), {
                type: 'bar',
                data: { labels: Object.keys(months), datasets: [{ label: 'New subscribers', data: Object.values(months), backgroundColor: brand, borderRadius: 6, maxBarThickness: 40 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
            });
            const composition = document.getElementById('composition');
            if (composition) {
                new Chart(composition, {
                    type: 'doughnut',
                    data: { labels: ['Subscribed', 'Unsubscribed', 'Unconfirmed', 'Blacklisted'], datasets: [{ data: @js([$counts['subscribed'], $counts['unsubscribed'], $counts['unconfirmed'], $counts['blacklisted']]), backgroundColor: [brand, '#f87171', '#fbbf24', '#8b8b93'], borderWidth: 0 }] },
                    options: { cutout: '72%', plugins: { legend: { display: false } } },
                });
            }
        </script>
    @endpush
</x-layouts.app>

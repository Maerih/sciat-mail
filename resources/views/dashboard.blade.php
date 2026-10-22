<x-layouts.app title="Dashboard">
    @php
        $nearLimit = collect($quota)->filter(fn ($q) => $q['limit'] !== null && $q['used'] >= $q['limit'] * \App\Services\Quota::WARN_AT);
        $latest = $recentCampaigns->firstWhere('status', 'done');
        $pct = fn ($used, $limit) => $limit ? min(100, round($used / max(1, $limit) * 100)) : null;
    @endphp

    {{-- Hero --}}
    <div class="mb-7 flex flex-wrap items-center justify-between gap-6">
        <div class="min-w-0">
            <h1 class="text-[28px] leading-tight font-bold tracking-tight">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="mt-1.5 text-sm text-[var(--muted)]">Here's what's happening with {{ $workspace->name }}'s email marketing.</p>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-[var(--muted)]">
                @if ($latest)
                    <span class="flex items-center gap-1.5"><x-icon name="mail" class="size-4" /><span class="font-semibold text-[var(--text)]">{{ $latest->name }}</span> — {{ $latest->finished_at?->diffForHumans() }}</span>
                @endif
                <span class="flex items-center gap-1.5"><x-icon name="group" class="size-4" /><span class="font-semibold text-[var(--text)]">{{ number_format($subscriberTotal) }}</span> contacts across all lists</span>
            </div>
        </div>
        <div class="flex gap-2">
            @if ($firstList = \App\Models\MailList::forWorkspace()->first())
                <a href="{{ route('lists.import.create', $firstList) }}" class="btn-secondary"><x-icon name="upload_file" class="size-4.5" /> Import contacts</a>
            @endif
            <a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="add" class="size-4.5" /> New campaign</a>
        </div>
    </div>

    @if ($nearLimit->isNotEmpty())
        <div class="mb-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="warning" class="size-5" />
            <span>This client is close to or over its {{ $nearLimit->keys()->map(fn ($k) => $k === 'emails' ? 'monthly email' : 'contact')->implode(' and ') }} limit.
                @if (auth()->user()->isAdmin()) <a href="{{ route('workspaces.edit') }}" class="underline">Adjust limits</a>@endif</span>
        </div>
    @endif

    @if ($serverCount === 0 || $verifiedDomains === 0 || $listCount === 0)
        <div class="card card-body mb-6">
            <div class="flex items-center justify-between">
                <div><h2 class="card-title">Get this client ready to send</h2><p class="text-xs text-[var(--muted)]">Complete these steps to start sending.</p></div>
                <span class="text-xs text-[var(--muted)]">{{ collect([$serverCount > 0, $verifiedDomains > 0, $listCount > 0])->filter()->count() }} of 3 completed</span>
            </div>
            <ol class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    [$serverCount > 0, 'Connect a sending server', 'SMTP, Amazon SES, SendGrid or Mailgun.', route('sending.servers.select'), 'dns'],
                    [$verifiedDomains > 0, 'Authenticate a sending domain', 'Publish DKIM, SPF and DMARC records.', route('sending.domains.index'), 'language'],
                    [$listCount > 0, 'Create a list and add contacts', 'Import a CSV or embed a signup form.', route('lists.create'), 'group_add'],
                ] as [$done, $label, $hint, $url, $icon])
                    <li>
                        <a href="{{ $url }}" class="flex h-full gap-3 rounded-lg border border-[var(--line)] p-3.5 transition hover:border-brand-500/60">
                            <span @class(['icon-tile size-9', 'bg-emerald-50 text-emerald-600' => $done, 'bg-[var(--surface-2)] text-[var(--muted)]' => ! $done])>
                                <x-icon :name="$done ? 'check_circle' : $icon" class="size-5" :fill="$done" />
                            </span>
                            <span><span @class(['block text-sm font-semibold', 'line-through opacity-60' => $done])>{{ $label }}</span>
                                <span class="block text-xs text-[var(--muted)]">{{ $hint }}</span></span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Resource cards --}}
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['Lists', $listCount, null, null, 'format_list_bulleted', 'bg-emerald-50 text-emerald-600', route('lists.index')],
            ['Campaigns', $campaignCount, null, null, 'mail', 'bg-blue-50 text-blue-600', route('campaigns.index')],
            ['Subscribers', $subscriberTotal, $quota['contacts']['used'], $quota['contacts']['limit'], 'group', 'bg-violet-50 text-violet-600', route('lists.index')],
        ] as [$label, $value, $used, $limit, $icon, $tile, $url])
            <a href="{{ $url }}" class="card card-body block transition hover:border-brand-500/50">
                <div class="flex items-start gap-3.5">
                    <span class="icon-tile {{ $tile }}"><x-icon :name="$icon" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="stat-label">{{ $label }}</div>
                        <div class="mt-0.5 text-[26px] leading-tight font-bold tabular-nums">{{ number_format($value) }}</div>
                        @if ($limit !== null)
                            <div class="mt-2 flex justify-between text-[11px] text-[var(--muted)]"><span>{{ $pct($used, $limit) }}%</span><span>{{ number_format($limit) }} max</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $pct($used, $limit) }}%"></div></div>
                        @elseif ($label === 'Campaigns')
                            <div class="mt-1 text-xs text-[var(--muted)]">{{ $automationCount }} active automation{{ $automationCount === 1 ? '' : 's' }}</div>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Performance cards --}}
    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Total sent', number_format($sent), 'send', 'bg-emerald-50 text-emerald-600', $quota['emails']['limit'] ? number_format($quota['emails']['used']).' / '.number_format($quota['emails']['limit']).' this month' : null],
            ['Open rate', $openRate.'%', 'drafts', 'bg-emerald-50 text-emerald-600', null],
            ['Click rate', $clickRate.'%', 'ads_click', 'bg-blue-50 text-blue-600', null],
            ['Bounce rate', $bounceRate.'%', 'person_alert', 'bg-amber-50 text-amber-600', 'Complaints '.$complaintRate.'%'],
        ] as [$label, $value, $icon, $tile, $hint])
            <div class="card card-body">
                <div class="flex items-start justify-between">
                    <span class="icon-tile {{ $tile }}"><x-icon :name="$icon" class="size-5" /></span>
                    <span class="text-[11px] text-[var(--muted)]">Last 30 days</span>
                </div>
                <div class="mt-3 text-[26px] leading-tight font-bold tabular-nums">{{ $value }}</div>
                <div class="text-xs text-[var(--muted)]">{{ $label }}@if ($hint) · {{ $hint }}@endif</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Sending activity</h2><p class="text-xs text-[var(--muted)]">Email volume over time</p></div></div>
            <div class="card-body">
                @if (array_sum($chart['sent']) === 0)
                    <div class="flex flex-col items-center py-12 text-center">
                        <span class="icon-tile size-12 bg-[var(--surface-2)] text-[var(--muted)]"><x-icon name="bar_chart" class="size-6" /></span>
                        <p class="mt-3 text-sm font-semibold">No sending activity in this window</p>
                        <p class="text-xs text-[var(--muted)]">Once campaigns start sending, daily volume appears here.</p>
                    </div>
                @else
                    <canvas id="activity" height="110"></canvas>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Deliverability health</h2><p class="text-xs text-[var(--muted)]">Keep both rates in the green</p></div></div>
            <div class="card-body space-y-5">
                @foreach ([['Bounce rate', $bounceRate, \App\Http\Controllers\DashboardController::BOUNCE_WARNING], ['Complaint rate', $complaintRate, \App\Http\Controllers\DashboardController::COMPLAINT_WARNING]] as [$label, $value, $limit])
                    <div>
                        <div class="flex justify-between text-sm"><span class="text-[var(--muted)]">{{ $label }}</span><span @class(['font-semibold tabular-nums', 'text-red-600' => $value > $limit])>{{ $value }}%</span></div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-[var(--surface-2)]"><div @class(['h-1.5 rounded-full', 'bg-red-500' => $value > $limit, 'bg-emerald-500' => $value <= $limit]) style="width: {{ min(100, $limit > 0 ? $value / ($limit * 2) * 100 : 0) }}%"></div></div>
                        <p class="help">Keep below {{ $limit }}%</p>
                    </div>
                @endforeach
                <div class="border-t border-[var(--line)] pt-4">
                    <x-quota-meter label="Emails this month" :used="$quota['emails']['used']" :limit="$quota['emails']['limit']" />
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><div><h2 class="card-title">Recent campaigns</h2><p class="text-xs text-[var(--muted)]">Your latest campaign performance</p></div>
                <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-brand-600 hover:underline">View all →</a></div>
            @forelse ($recentCampaigns as $campaign)
                <a href="{{ route('campaigns.show', $campaign) }}" class="flex items-center justify-between gap-4 border-b border-[var(--line)] px-6 py-3.5 transition last:border-0 hover:bg-[var(--surface-2)]">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="icon-tile size-9 bg-[var(--surface-2)] text-[var(--muted)]"><x-icon name="mail" class="size-4.5" /></span>
                        <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $campaign->name }}</span>
                            <span class="block truncate text-xs text-[var(--muted)]">{{ $campaign->created_at->diffForHumans() }}</span></span>
                    </span>
                    <span class="flex items-center gap-4 text-sm">
                        @if ($campaign->sent_count)<span class="text-[var(--muted)] tabular-nums">{{ number_format($campaign->sent_count) }} sent</span>@endif
                        <x-status-badge :status="$campaign->status" />
                    </span>
                </a>
            @empty
                <x-empty title="No campaigns yet" icon="mail">Create your first campaign for this client.
                    <x-slot:action><a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="add" class="size-4.5" /> New campaign</a></x-slot:action>
                </x-empty>
            @endforelse
        </div>
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Top campaigns</h2><p class="text-xs text-[var(--muted)]">Best performing by open rate</p></div></div>
                <div class="card-body space-y-3">
                    @forelse ($topCampaigns as $i => $row)
                        <a href="{{ route('campaigns.show', $row['campaign']) }}" class="flex items-center gap-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-[var(--surface-2)] text-xs font-bold">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $row['campaign']->name }}</span>
                            <span class="text-sm font-semibold text-emerald-600 tabular-nums">{{ $row['stats']['open_rate'] }}%</span>
                        </a>
                    @empty
                        <p class="text-sm text-[var(--muted)]">No completed campaigns yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Subscriber overview</h2><p class="text-xs text-[var(--muted)]">Contact health across all lists</p></div></div>
                <div class="card-body space-y-2.5">
                    @foreach (['subscribed' => 'bg-emerald-500', 'unconfirmed' => 'bg-amber-500', 'unsubscribed' => 'bg-gray-400', 'blacklisted' => 'bg-red-500'] as $status => $dot)
                        @php $n = (int) ($audience[$status] ?? 0); @endphp
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2"><span class="size-2 rounded-full {{ $dot }}"></span>{{ ucfirst($status) }}</span>
                            <span class="tabular-nums text-[var(--muted)]">{{ number_format($n) }} <span class="text-xs">({{ $subscriberTotal ? round($n / $subscriberTotal * 100, 1) : 0 }}%)</span></span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const el = document.getElementById('activity');
            if (el) {
                const css = getComputedStyle(document.documentElement);
                const brand = css.getPropertyValue('--color-brand-500').trim() || '#4ade80';
                const muted = css.getPropertyValue('--muted').trim();
                const line = css.getPropertyValue('--line').trim();
                const data = @js($chart);
                new Chart(el, {
                    type: 'bar',
                    data: { labels: data.labels, datasets: [
                        { label: 'Sent', data: data.sent, backgroundColor: brand, borderRadius: 4, maxBarThickness: 18 },
                        { label: 'Unique opens', data: data.opens, type: 'line', borderColor: '#60a5fa', backgroundColor: 'transparent', tension: 0.35, pointRadius: 0 },
                    ] },
                    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, color: muted } } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: line } }, x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 10 } } } },
                });
            }
        </script>
    @endpush
</x-layouts.app>

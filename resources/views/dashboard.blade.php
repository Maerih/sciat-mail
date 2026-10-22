<x-layouts.app title="Dashboard">
    <x-page-header :title="$workspace->name" subtitle="Last 30 days of email activity for this client.">
        <x-slot:actions>
            <a href="{{ route('lists.index') }}" class="btn-secondary"><x-icon name="users" /> Audience</a>
            <a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="plus" /> New campaign</a>
        </x-slot:actions>
    </x-page-header>

    @if ($serverCount === 0 || $verifiedDomains === 0 || $listCount === 0)
        <div class="card card-body mb-6">
            <h2 class="card-title">Get this client ready to send</h2>
            <ol class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    [$serverCount > 0, 'Connect a sending server', 'SMTP, Amazon SES, SendGrid or Mailgun.', route('sending.servers.select')],
                    [$verifiedDomains > 0, 'Authenticate a sending domain', 'Publish DKIM, SPF and DMARC records.', route('sending.domains.index')],
                    [$listCount > 0, 'Create a list and add contacts', 'Import a CSV or embed a signup form.', route('lists.create')],
                ] as [$done, $label, $hint, $url])
                    <li>
                        <a href="{{ $url }}" class="flex h-full gap-3 rounded-lg border border-gray-200 p-3 hover:border-brand-500/50 hover:bg-brand-50/40">
                            <span @class(['flex size-6 shrink-0 items-center justify-center rounded-full text-xs', 'bg-emerald-500 text-white' => $done, 'border border-gray-300 text-gray-400' => ! $done])>
                                @if ($done)<x-icon name="check" class="size-3.5" />@else{{ $loop->iteration }}@endif
                            </span>
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                                <span class="block text-xs text-gray-500">{{ $hint }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @php
        $nearLimit = collect($quota)->filter(fn ($q) => $q['limit'] !== null && $q['used'] >= $q['limit'] * \App\Services\Quota::WARN_AT);
    @endphp
    @if ($nearLimit->isNotEmpty())
        <div class="mb-6 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="warning" class="size-5" />
            <span>This client is close to or over its {{ $nearLimit->keys()->map(fn ($k) => $k === 'emails' ? 'monthly email' : 'contact')->implode(' and ') }} limit.
                @if (auth()->user()->isAdmin()) <a href="{{ route('workspaces.edit') }}" class="underline">Adjust limits</a>@endif</span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Emails sent" :value="number_format($sent)" :hint="$campaignCount.' campaigns in total'" />
        <x-stat label="Open rate" :value="$openRate.'%'" />
        <x-stat label="Click rate" :value="$clickRate.'%'" />
        <x-stat label="Subscribers" :value="number_format($subscriberTotal)" :hint="$listCount.' lists'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Sending activity</h2><span class="text-xs text-gray-500">Emails sent and unique opens per day</span></div>
            <div class="card-body"><canvas id="activity" height="110"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Deliverability health</h2></div>
            <div class="card-body space-y-4">
                <x-quota-meter label="Emails this month" :used="$quota['emails']['used']" :limit="$quota['emails']['limit']" />
                <x-quota-meter label="Contacts" :used="$quota['contacts']['used']" :limit="$quota['contacts']['limit']" class="border-b border-gray-100 pb-4" />
                @foreach ([['Bounce rate', $bounceRate, \App\Http\Controllers\DashboardController::BOUNCE_WARNING], ['Complaint rate', $complaintRate, \App\Http\Controllers\DashboardController::COMPLAINT_WARNING]] as [$label, $value, $limit])
                    <div>
                        <div class="flex justify-between text-sm"><span class="text-gray-600">{{ $label }}</span><span @class(['font-semibold tabular-nums', 'text-red-600' => $value > $limit, 'text-gray-900' => $value <= $limit])>{{ $value }}%</span></div>
                        <div class="mt-1.5 h-2 rounded-full bg-gray-100"><div @class(['h-2 rounded-full', 'bg-red-500' => $value > $limit, 'bg-emerald-500' => $value <= $limit]) style="width: {{ min(100, $limit > 0 ? $value / ($limit * 2) * 100 : 0) }}%"></div></div>
                        <p class="help">Keep below {{ $limit }}% to protect sender reputation.</p>
                    </div>
                @endforeach
                <div class="border-t border-gray-100 pt-4">
                    <div class="mb-2 text-sm text-gray-600">Audience</div>
                    @foreach (\App\Models\Subscriber::STATUSES as $status)
                        <div class="flex justify-between py-0.5 text-sm"><x-status-badge :status="$status" /><span class="tabular-nums">{{ number_format($audience[$status] ?? 0) }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Recent campaigns</h2><a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all &rarr;</a></div>
        @forelse ($recentCampaigns as $campaign)
            <a href="{{ route('campaigns.show', $campaign) }}" class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-3 last:border-0 hover:bg-gray-50">
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-gray-900">{{ $campaign->name }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ $campaign->subject ?: 'No subject yet' }}</span>
                </span>
                <span class="flex items-center gap-4 text-sm">
                    @if ($campaign->sent_count)<span class="text-gray-500 tabular-nums">{{ number_format($campaign->sent_count) }} sent</span>@endif
                    <x-status-badge :status="$campaign->status" />
                </span>
            </a>
        @empty
            <x-empty title="No campaigns yet" icon="megaphone">Create your first campaign for this client.
                <x-slot:action><a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="plus" /> New campaign</a></x-slot:action>
            </x-empty>
        @endforelse
    </div>

    @push('scripts')
        <script type="module">
            const data = @js($chart);
            new Chart(document.getElementById('activity'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'Sent', data: data.sent, backgroundColor: 'oklch(0.58 0.19 265 / 0.75)', borderRadius: 3 },
                        { label: 'Unique opens', data: data.opens, type: 'line', borderColor: 'oklch(0.7 0.15 160)', backgroundColor: 'transparent', tension: 0.3, pointRadius: 0 },
                    ],
                },
                options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
            });
        </script>
    @endpush
</x-layouts.app>

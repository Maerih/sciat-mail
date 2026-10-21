<x-layouts.app :title="$list->name">
    <x-page-header :title="$list->name" :subtitle="number_format($total).' contacts'" :back="route('lists.index')">
        <x-slot:actions>
            <a href="{{ route('forms.create', ['list' => $list->uid]) }}" class="btn-secondary"><x-icon name="form" /> Signup form</a>
            <a href="{{ route('lists.import.create', $list) }}" class="btn-secondary"><x-icon name="upload" /> Import</a>
            <a href="{{ route('lists.subscribers.create', $list) }}" class="btn-primary"><x-icon name="plus" /> Add subscriber</a>
        </x-slot:actions>
    </x-page-header>
    @include('lists._tabs')

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Subscribed" :value="number_format($counts['subscribed'])" tone="green" />
        <x-stat label="Unconfirmed" :value="number_format($counts['unconfirmed'])" hint="Awaiting double opt-in" />
        <x-stat label="Unsubscribed" :value="number_format($counts['unsubscribed'])" />
        <x-stat label="Blacklisted" :value="number_format($counts['blacklisted'])" tone="red" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">New subscribers — last 30 days</h2></div>
            <div class="card-body"><canvas id="growth" height="110"></canvas></div>
        </div>
        <div class="card card-body space-y-4">
            <h2 class="card-title">Engagement</h2>
            <div class="flex justify-between text-sm"><span class="text-gray-600">Average open rate</span><span class="font-semibold">{{ $openRate }}%</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-600">Average click rate</span><span class="font-semibold">{{ $clickRate }}%</span></div>
            <div class="border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Email verification</h3>
                    @if (in_array($list->verification_job_status, ['queued', 'running']))
                        <span class="badge-blue">{{ ucfirst($list->verification_job_status) }}…</span>
                    @else
                        <form method="POST" action="{{ route('lists.verify', $list) }}">@csrf<button class="btn-secondary btn-sm">Verify list</button></form>
                    @endif
                </div>
                @foreach (\App\Models\VerificationServer::STATUSES as $v)
                    <a href="{{ route('lists.subscribers.index', [$list, 'verification' => $v]) }}" class="mt-1 flex justify-between text-sm text-gray-600 hover:text-gray-900"><span>{{ ucfirst($v) }}</span><span class="tabular-nums">{{ number_format($verification[$v] ?? 0) }}</span></a>
                @endforeach
                <p class="help">Undeliverable addresses are skipped by every campaign.</p>
            </div>
            <div class="border-t border-gray-100 pt-4 text-sm text-gray-600">
                <div class="flex justify-between"><span>Double opt-in</span><span>{{ $list->double_optin ? 'On' : 'Off' }}</span></div>
                <div class="mt-1 flex justify-between"><span>Welcome email</span><span>{{ $list->send_welcome_email ? 'On' : 'Off' }}</span></div>
                <div class="mt-1 flex justify-between"><span>Sender</span><span class="truncate pl-3">{{ $list->from_email }}</span></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const days = @js($days);
            new Chart(document.getElementById('growth'), {
                type: 'bar',
                data: { labels: Object.keys(days).map(d => d.slice(5)), datasets: [{ label: 'New subscribers', data: Object.values(days), backgroundColor: 'oklch(0.58 0.19 265 / 0.75)', borderRadius: 3 }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
            });
        </script>
    @endpush
</x-layouts.app>

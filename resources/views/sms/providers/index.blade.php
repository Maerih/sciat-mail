<x-layouts.app title="SMS providers">
    <x-page-header title="SMS providers" subtitle="The gateways that deliver your text messages." />
    <x-banner icon="sms" title="Connect an SMS gateway" text="Use Africa's Talking for direct routes to Safaricom, Airtel and Telkom in Kenya and across East Africa, Twilio, Infobip or Vonage worldwide, or plug in any local aggregator with the Custom HTTP API."
        :stats="[['sms', $providers->count().' connected'], ['check_circle', $providers->where('status', 'active')->count().' active']]" />

    @if ($providers->isNotEmpty())
        <div class="card mb-6 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Provider</th><th>Type</th><th>Sender ID</th><th>Status</th><th>Last test</th><th></th></tr></thead>
                <tbody>
                    @foreach ($providers as $provider)
                        <tr>
                            <td><a href="{{ route('sms.providers.edit', $provider) }}" class="font-medium hover:text-brand-600">{{ $provider->name }}</a>
                                @if ($provider->is_default)<span class="badge-green ml-1">Default</span>@endif
                                @if ($provider->isShared())<span class="badge-purple ml-1">Shared</span>@endif</td>
                            <td>{{ $provider->typeLabel() }}</td>
                            <td class="font-mono text-xs">{{ $provider->sender_id ?: '—' }}</td>
                            <td><x-status-badge :status="$provider->status" /></td>
                            <td>@if ($provider->last_tested_at)<span @class(['text-red-600' => $provider->last_error, 'text-emerald-600' => ! $provider->last_error])>{{ $provider->last_error ? 'Failed' : 'OK' }}</span> <span class="text-xs text-[var(--muted)]">{{ $provider->last_tested_at->diffForHumans() }}</span>@else<span class="text-[var(--muted)]">Never</span>@endif</td>
                            <td class="text-right whitespace-nowrap">
                                @unless ($provider->is_default)<form method="POST" action="{{ route('sms.providers.default', $provider) }}" class="inline">@csrf<button class="btn-ghost btn-sm">Make default</button></form>@endunless
                                <a href="{{ route('sms.providers.edit', $provider) }}" class="btn-ghost btn-sm">Configure</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="mb-3 text-sm font-semibold text-[var(--muted)] uppercase">Add a provider</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($types as $key => $type)
            <a href="{{ route('sms.providers.create', ['type' => $key]) }}" class="card card-body group transition hover:border-brand-500/60">
                <div class="flex items-center justify-between">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ strtoupper(substr($type['label'], 0, 2)) }}</span>
                    <span class="badge-gray">{{ $type['region'] }}</span>
                </div>
                <h3 class="mt-3 font-semibold group-hover:text-brand-600">{{ $type['label'] }}</h3>
                <p class="mt-1 text-sm text-[var(--muted)]">{{ $type['description'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>

<x-layouts.app title="API & webhooks">
    <x-page-header title="API & webhooks" subtitle="Connect this client's website, shop or CRM: manage contacts, send transactional email and receive events.">
        <x-slot:actions><a href="{{ route('api.docs') }}" class="btn-secondary">API reference</a></x-slot:actions>
    </x-page-header>

    @if ($newToken)
        <div class="card card-body mb-6 border-emerald-300 bg-emerald-50" x-data="copy(@js($newToken))">
            <h2 class="text-sm font-semibold text-emerald-900">Your new API token</h2>
            <p class="text-sm text-emerald-800">Copy it now and store it safely — for security it is never shown again.</p>
            <div class="mt-2 flex gap-2"><div class="code flex-1">{{ $newToken }}</div><button type="button" class="btn-primary" @click="copy()" x-text="copied ? 'Copied!' : 'Copy'"></button></div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">API tokens</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Token</th><th>Last used</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $token->name }}</td>
                                <td><code class="text-xs">{{ $token->prefix }}…</code></td>
                                <td class="text-gray-500">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-right"><x-confirm-delete :action="route('api.tokens.destroy', $token->id)" label="Revoke" :message="'Revoke '.$token->name.'? Integrations using it stop working.'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-gray-500">No tokens yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <form method="POST" action="{{ route('api.tokens.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="card-title">Create a token</h2>
            <x-field name="name" label="Name" placeholder="e.g. Website signup, Shopify sync" required />
            <p class="help">A token can read and change everything in <strong>{{ $currentWorkspace->name }}</strong> — and nothing in other clients.</p>
            <button class="btn-primary w-full">Create token</button>
        </form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @forelse ($webhooks as $webhook)
                <div class="card">
                    <div class="card-header">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-900">{{ $webhook->url }}</div>
                            <div class="text-xs text-gray-500">{{ collect($webhook->events)->map(fn ($e) => \App\Models\Webhook::EVENTS[$e] ?? $e)->implode(' · ') }}</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($webhook->active)<span class="badge-green">Active</span>@else<span class="badge-red">Disabled</span>@endif
                            <form method="POST" action="{{ route('api.webhooks.test', $webhook) }}">@csrf<button class="btn-ghost btn-sm" @disabled(! $webhook->active)>Send test</button></form>
                            <form method="POST" action="{{ route('api.webhooks.toggle', $webhook) }}">@csrf<button class="btn-ghost btn-sm">{{ $webhook->active ? 'Disable' : 'Enable' }}</button></form>
                            <x-confirm-delete :action="route('api.webhooks.destroy', $webhook)" label="Remove" />
                        </div>
                    </div>
                    <div class="border-b border-gray-100 px-5 py-2 text-xs text-gray-500" x-data="{ show: false }">
                        Signing secret: <code x-show="show" x-cloak>{{ $webhook->secret }}</code><button type="button" x-show="!show" @click="show = true" class="text-brand-600 hover:underline">reveal</button>
                        · verify <code>X-SM-Signature: sha256=HMAC_SHA256(raw body, secret)</code>
                    </div>
                    @if ($webhook->deliveries->isNotEmpty())
                        <table class="table">
                            <tbody>
                                @foreach ($webhook->deliveries as $delivery)
                                    <tr>
                                        <td class="w-40"><code class="text-xs">{{ $delivery->event }}</code></td>
                                        <td>@if ($delivery->status === 'success')<span class="badge-green">{{ $delivery->response_status }}</span>@elseif ($delivery->status === 'failed')<span class="badge-red">Failed</span>@else<span class="badge-yellow">{{ $delivery->response_status ?? 'Pending' }}</span>@endif</td>
                                        <td class="text-xs text-gray-500">attempt {{ $delivery->attempts }}</td>
                                        <td class="max-w-xs truncate text-xs text-gray-500" title="{{ $delivery->response_body }}">{{ $delivery->response_body }}</td>
                                        <td class="text-right text-xs whitespace-nowrap text-gray-400">{{ $delivery->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="px-5 py-4 text-sm text-gray-500">No deliveries yet.</p>
                    @endif
                </div>
            @empty
                <div class="card"><x-empty title="No webhooks yet" icon="link">Get notified in real time when contacts subscribe, open, click, bounce or complain.</x-empty></div>
            @endforelse
        </div>
        <form method="POST" action="{{ route('api.webhooks.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="card-title">Add a webhook endpoint</h2>
            <x-field name="url" type="url" label="Endpoint URL" placeholder="https://example.com/hooks/email" required />
            <div>
                <span class="label">Events</span>
                <div class="space-y-1.5">
                    @foreach (\App\Models\Webhook::EVENTS as $event => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="events[]" value="{{ $event }}" class="rounded border-gray-300 text-brand-600" @checked(in_array($event, old('events', ['subscriber.subscribed', 'subscriber.unsubscribed'])))> {{ $label }} <code class="text-xs text-gray-400">{{ $event }}</code></label>
                    @endforeach
                </div>
                @error('events')<p class="error">{{ $message }}</p>@enderror
            </div>
            <p class="help">Failed calls are retried 5 times over ~1.5 hours; endpoints failing {{ \App\Models\Webhook::DISABLE_AFTER }} times in a row are disabled.</p>
            <button class="btn-primary w-full">Add endpoint</button>
        </form>
    </div>
</x-layouts.app>

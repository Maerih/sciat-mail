<x-layouts.app title="Email verification">
    <x-page-header title="Email verification" subtitle="Clean lists before sending: undeliverable addresses are skipped by every campaign, protecting this client's sender reputation." />
    <x-banner icon="mark_email_read" title="Verify before you send" text="Remove invalid, disposable and risky addresses before a campaign goes out. Fewer bounces means better inbox placement for everyone on your servers."
        :stats="[['dns', $servers->count().' verification services'], ['check_circle', $active ? 'Using '.e($active->name) : 'No active service']]" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body">
                <h2 class="card-title">Currently used</h2>
                <p class="mt-1 text-sm text-gray-600"><strong>{{ $active->name }}</strong> — {{ \App\Models\VerificationServer::PROVIDERS[$active->provider]['description'] }}</p>
                <p class="help mt-2">Run verification from a list's overview, or select subscribers and choose “Verify email addresses”.</p>
            </div>
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Service</th><th>Provider</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($servers as $server)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $server->name }} @if ($server->is_default)<span class="badge-blue ml-1">In use</span>@endif</td>
                                <td>{{ $server->providerLabel() }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @unless ($server->is_default)
                                        <form method="POST" action="{{ route('sending.verification.default', $server) }}" class="inline">@csrf<button class="btn-ghost btn-sm">Use this</button></form>
                                    @endunless
                                    <x-confirm-delete :action="route('sending.verification.destroy', $server)" label="Remove" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-8 text-center text-gray-500">No service added — the free built-in check is used.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <form method="POST" action="{{ route('sending.verification.store') }}" class="card card-body space-y-3" x-data="{ provider: 'emailable' }">
            @csrf
            <h2 class="card-title">Add a verification service</h2>
            <div>
                <label class="label">Provider</label>
                <select name="provider" class="input" x-model="provider">
                    @foreach (\App\Models\VerificationServer::PROVIDERS as $key => $p)<option value="{{ $key }}">{{ $p['label'] }}</option>@endforeach
                </select>
            </div>
            <x-field name="name" label="Name" placeholder="e.g. Emailable — main account" required />
            <div x-show="provider !== 'builtin'"><x-field name="api_key" type="password" label="API key" autocomplete="off" /></div>
            <x-field name="test_email" type="email" label="Test with an address (optional)" help="Runs one verification right after saving. Uses one credit." />
            <button class="btn-primary w-full">Save</button>
        </form>
    </div>
</x-layouts.app>

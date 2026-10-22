<x-layouts.app title="Tracking domains">
    <x-page-header title="Tracking domains" subtitle="Open, click and unsubscribe links use this client's own domain instead of the platform's, so its reputation stays its own." />
    <x-banner icon="ads_click" title="Brand your tracking links" text="Open pixels, click redirects and unsubscribe links use your own domain, so links look trustworthy and your reputation is never shared with anyone else."
        :stats="[['link', $domains->count().' domains'], ['verified', $domains->where('verified', true)->count().' verified'], ['star', $domains->firstWhere('is_default', true)?->host ?? 'No default yet']]" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @if ($domains->isEmpty())
                <x-empty title="No tracking domains" icon="link">Links currently use {{ config('app.url') }}.</x-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Host</th><th>Status</th><th>Default</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($domains as $domain)
                                <tr>
                                    <td class="font-medium text-gray-900">{{ $domain->scheme }}://{{ $domain->host }}</td>
                                    <td>@if ($domain->verified)<span class="badge-green">Verified</span>@else<span class="badge-yellow">CNAME not found</span>@endif</td>
                                    <td>@if ($domain->is_default)<span class="badge-blue">Default</span>@elseif ($domain->verified)<form method="POST" action="{{ route('sending.tracking-domains.default', $domain) }}">@csrf<button class="btn-ghost btn-sm">Make default</button></form>@endif</td>
                                    <td class="text-right whitespace-nowrap">
                                        <form method="POST" action="{{ route('sending.tracking-domains.verify', $domain) }}" class="inline">@csrf<button class="btn-secondary btn-sm">Verify</button></form>
                                        <x-confirm-delete :action="route('sending.tracking-domains.destroy', $domain)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('sending.tracking-domains.store') }}" class="card card-body space-y-3">
                @csrf
                <h2 class="card-title">Add tracking domain</h2>
                <x-field name="host" label="Hostname" placeholder="links.example.com" required />
                <div><label class="label">Protocol</label><select name="scheme" class="input"><option value="https">https (recommended)</option><option value="http">http</option></select></div>
                <button class="btn-primary w-full">Add</button>
            </form>
            <div class="card card-body text-sm text-gray-600">
                <h2 class="card-title mb-2">DNS setup</h2>
                <p>Create a <strong>CNAME</strong> record for the hostname pointing to:</p>
                <div class="code mt-2">{{ $target }}</div>
                <p class="help mt-2">For https, the web server must also hold a TLS certificate for the hostname.</p>
            </div>
        </div>
    </div>
</x-layouts.app>

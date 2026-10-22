<x-layouts.app title="Sending domains">
    <x-page-header title="Sending domains" subtitle="Authenticate the domains this client sends from. Verified domains get DKIM-signed mail and better inbox placement." />
    <x-banner icon="verified_user" title="Authenticate your sending domains" text="SPF, DKIM and DMARC prove your emails really come from you. Gmail and Yahoo require them for bulk senders — verified domains land in the inbox, not spam."
        :stats="[['dns', $domains->count().' domains'], ['verified', $domains->filter->isVerified()->count().' verified'], ['pending', $domains->reject->isVerified()->count().' pending DNS']]" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @if ($domains->isEmpty())
                <x-empty title="No domains yet" icon="globe">Add the domain used in the From address (e.g. example.com).</x-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Domain</th><th>Ownership</th><th>DKIM</th><th>SPF</th><th>DMARC</th><th>Checked</th></tr></thead>
                        <tbody>
                            @foreach ($domains as $domain)
                                <tr>
                                    <td><a href="{{ route('sending.domains.show', $domain) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $domain->name }}</a></td>
                                    @foreach (['ownership_verified', 'dkim_verified', 'spf_verified', 'dmarc_verified'] as $flag)
                                        <td>@if ($domain->$flag)<span class="badge-green">OK</span>@else<span class="badge-yellow">Pending</span>@endif</td>
                                    @endforeach
                                    <td class="text-gray-500">{{ $domain->checked_at?->diffForHumans() ?? 'Never' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <form method="POST" action="{{ route('sending.domains.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="card-title">Add a domain</h2>
            <x-field name="name" label="Domain name" placeholder="example.com" required help="No protocol or path." />
            <button class="btn-primary w-full">Add domain</button>
        </form>
    </div>
</x-layouts.app>

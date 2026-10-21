<x-layouts.app title="Sending servers">
    <x-page-header title="Sending servers" subtitle="The services that actually deliver this client's email. Shared servers are available to every client.">
        <x-slot:actions><a href="{{ route('sending.servers.select') }}" class="btn-primary"><x-icon name="plus" /> Add server</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        @if ($servers->isEmpty())
            <x-empty title="No sending servers" icon="server">Connect SMTP, Amazon SES, SendGrid or Mailgun to start sending.
                <x-slot:action><a href="{{ route('sending.servers.select') }}" class="btn-primary">Add server</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Server</th><th>Type</th><th>Status</th><th>Limits</th><th>Last test</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($servers as $server)
                            <tr>
                                <td><a href="{{ route('sending.servers.edit', $server) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $server->name }}</a>
                                    @if ($server->isShared())<span class="badge-purple ml-1">Shared</span>@endif</td>
                                <td>{{ $server->typeLabel() }}</td>
                                <td><x-status-badge :status="$server->status" /></td>
                                <td class="text-gray-500">{{ $server->hourly_limit ? number_format($server->hourly_limit).'/h' : '∞/h' }} · {{ $server->daily_limit ? number_format($server->daily_limit).'/day' : '∞/day' }}</td>
                                <td>@if ($server->last_tested_at)<span @class(['text-red-600' => $server->last_error, 'text-emerald-600' => ! $server->last_error])>{{ $server->last_error ? 'Failed' : 'OK' }}</span> <span class="text-xs text-gray-400">{{ $server->last_tested_at->diffForHumans() }}</span>@else<span class="text-gray-400">Never</span>@endif</td>
                                <td class="text-right"><a href="{{ route('sending.servers.edit', $server) }}" class="btn-ghost btn-sm">Configure</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

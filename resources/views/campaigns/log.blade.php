<x-layouts.app :title="$logs[$log].' · '.$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')
    <div class="card">
        <form class="flex flex-wrap gap-2 border-b border-gray-100 p-3">
            <input name="q" value="{{ request('q') }}" placeholder="Search email…" class="input max-w-xs">
            @if ($log === 'deliveries')
                <select name="status" class="input w-auto" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'sent', 'failed', 'skipped', 'bounced', 'complained'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                @if ($log === 'deliveries')
                    <thead><tr><th>Recipient</th><th>Status</th><th>Server</th><th>Opens</th><th>Clicks</th><th>Sent</th><th>Error</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->email }}</td><td><x-status-badge :status="$row->status" /></td><td>{{ $row->sendingServer?->name ?? '—' }}</td>
                                <td>{{ $row->open_count }}</td><td>{{ $row->click_count }}</td>
                                <td class="whitespace-nowrap text-gray-500">{{ $row->sent_at?->setTimezone($currentWorkspace->timezone)->format('M j, H:i:s') ?? '—' }}</td>
                                <td class="max-w-xs truncate text-xs text-red-600" title="{{ $row->error }}">{{ $row->error }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-8 text-center text-gray-500">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                @else
                    <thead><tr><th>Recipient</th><th>Time</th>
                        @if ($log === 'clicks')<th>URL</th>@endif
                        @if (in_array($log, ['bounces', 'unsubscribes']))<th>Type</th>@endif
                        @if (in_array($log, ['bounces', 'complaints']))<th>Detail</th>@endif
                        @if (in_array($log, ['opens', 'clicks']))<th>IP</th><th>Device</th>@endif
                    </tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->delivery?->email }}</td>
                                <td class="whitespace-nowrap text-gray-500">{{ $row->created_at->setTimezone($currentWorkspace->timezone)->format('M j, H:i:s') }}</td>
                                @if ($log === 'clicks')<td class="max-w-sm truncate">{{ $row->url }}</td>@endif
                                @if (in_array($log, ['bounces', 'unsubscribes']))<td>{{ $row->subtype }}</td>@endif
                                @if (in_array($log, ['bounces', 'complaints']))<td class="max-w-sm truncate text-xs" title="{{ $row->detail }}">{{ $row->detail }}</td>@endif
                                @if (in_array($log, ['opens', 'clicks']))<td>{{ $row->ip }}</td><td class="max-w-xs truncate text-xs text-gray-500" title="{{ $row->user_agent }}">{{ $row->user_agent }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-500">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
        {{ $rows->links() }}
    </div>
</x-layouts.app>

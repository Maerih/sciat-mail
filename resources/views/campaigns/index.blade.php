<x-layouts.app title="Campaigns">
    <x-page-header title="Campaigns" subtitle="Design, schedule and track email campaigns for this client.">
        <x-slot:actions><a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="plus" /> New campaign</a></x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('campaigns.index') }}" @class(['badge px-3 py-1 text-sm', 'bg-gray-900 text-white' => ! request('status'), 'bg-white text-gray-700 ring-1 ring-gray-200' => request('status')])>All {{ $statusCounts->sum() }}</a>
        @foreach ($statusCounts as $status => $count)
            <a href="{{ route('campaigns.index', ['status' => $status]) }}" @class(['badge px-3 py-1 text-sm', 'bg-gray-900 text-white' => request('status') === $status, 'bg-white text-gray-700 ring-1 ring-gray-200' => request('status') !== $status])>{{ ucfirst($status) }} {{ $count }}</a>
        @endforeach
    </div>

    <div class="card">
        <form class="flex flex-wrap gap-2 border-b border-gray-100 p-3">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search name or subject…" class="input max-w-xs">
            <select name="sort" class="input w-auto" onchange="this.form.submit()"><option value="">Newest first</option><option value="name" @selected(request('sort') === 'name')>Name</option></select>
        </form>
        @if ($campaigns->isEmpty())
            <x-empty title="No campaigns found" icon="megaphone">
                <x-slot:action><a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="plus" /> New campaign</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td class="max-w-md">
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="block truncate font-medium text-gray-900 hover:text-brand-600">{{ $campaign->name }}</a>
                                    <span class="block truncate text-xs text-gray-500">{{ $campaign->subject ?: 'No subject yet' }} · {{ $campaign->isPlain() ? 'Plain text' : 'HTML' }}</span>
                                </td>
                                <td><x-status-badge :status="$campaign->status" /></td>
                                <td class="tabular-nums">{{ $campaign->recipients_count ? number_format($campaign->recipients_count) : '—' }}</td>
                                <td class="tabular-nums">{{ $campaign->sent_count ? number_format($campaign->sent_count) : '—' }}</td>
                                <td class="whitespace-nowrap text-gray-500">
                                    @if ($campaign->status === 'scheduled' && $campaign->scheduled_at)
                                        Sends {{ $campaign->scheduled_at->setTimezone($currentWorkspace->timezone)->format('M j, H:i') }}
                                    @else
                                        {{ ($campaign->started_at ?? $campaign->created_at)->setTimezone($currentWorkspace->timezone)->format('M j, Y') }}
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($campaign->isEditable())<a href="{{ route('campaigns.setup', $campaign) }}" class="btn-ghost btn-sm">Edit</a>@endif
                                    <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}" class="inline">@csrf<button class="btn-ghost btn-sm">Duplicate</button></form>
                                    @unless ($campaign->isRunning())
                                        <x-confirm-delete :action="route('campaigns.destroy', $campaign)" :message="'Delete '.$campaign->name.' and its report?'" />
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $campaigns->links() }}
        @endif
    </div>
</x-layouts.app>

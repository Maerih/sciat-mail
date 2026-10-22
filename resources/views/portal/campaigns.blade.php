<x-layouts.portal title="Campaigns">
    <x-page-header title="Campaigns" />
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Campaign</th><th>Sent</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    <tr>
                        <td><a href="{{ route('portal.campaign', $campaign) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $campaign->name }}</a>
                            <div class="truncate text-xs text-gray-500">{{ $campaign->subject }}</div></td>
                        <td class="tabular-nums">{{ number_format($campaign->sent_count) }}</td>
                        <td><x-status-badge :status="$campaign->status" /></td>
                        <td class="whitespace-nowrap text-gray-500">{{ $campaign->started_at?->setTimezone($portalWorkspace->timezone)->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-gray-500">No campaigns have been sent yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $campaigns->links() }}
    </div>
</x-layouts.portal>

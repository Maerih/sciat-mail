<x-page-header :title="$campaign->name" :subtitle="$campaign->subject" :back="route('campaigns.index')">
    <x-slot:actions>
        <x-status-badge :status="$campaign->status" />
        <a href="{{ route('campaigns.preview', $campaign) }}" target="_blank" class="btn-secondary"><x-icon name="eye" /> Preview</a>
        @if ($campaign->isRunning())
            <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">@csrf<button class="btn-secondary">Pause</button></form>
        @endif
        @if (in_array($campaign->status, ['paused', 'error']))
            <form method="POST" action="{{ route('campaigns.resume', $campaign) }}">@csrf<button class="btn-primary">Resume sending</button></form>
        @endif
        @if (in_array($campaign->status, ['queued', 'sending', 'paused', 'error']))
            <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel this campaign? Remaining recipients will not be emailed.')">@csrf<button class="btn-ghost text-red-600 hover:bg-red-50">Cancel</button></form>
        @endif
        <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}">@csrf<button class="btn-secondary">Duplicate</button></form>
    </x-slot:actions>
</x-page-header>

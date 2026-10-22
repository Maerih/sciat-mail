<x-page-header :title="$campaign->name" :subtitle="$campaign->subject" :back="$campaign->isAutomationEmail() ? route('automations.show', $campaign->automation_id ? \App\Models\Automation::find($campaign->automation_id) : null) : route('campaigns.index')">
    <x-slot:actions>
        <x-status-badge :status="$campaign->status" />
        <a href="{{ route('campaigns.preview', $campaign) }}" target="_blank" class="btn-secondary"><x-icon name="eye" /> Preview</a>
        @unless ($campaign->isAutomationEmail())
        @if ($campaign->isRunning())
            <form method="POST" action="{{ route('campaigns.pause', $campaign) }}">@csrf<button class="btn-secondary">Pause</button></form>
        @endif
        @if (in_array($campaign->status, ['paused', 'error']))
            <form method="POST" action="{{ route('campaigns.resume', $campaign) }}">@csrf<button class="btn-primary">Resume sending</button></form>
        @endif
        @if (in_array($campaign->status, ['queued', 'sending', 'paused', 'error']))
            <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel this campaign? Remaining recipients will not be emailed.')">@csrf<button class="btn-ghost text-red-600 hover:bg-red-50">Cancel</button></form>
        @endif
        @if ($campaign->status === 'done')
            <form method="POST" action="{{ route('sms.campaigns.store', ['campaign' => $campaign->uid]) }}">@csrf<button class="btn-secondary" title="Text the recipients who did not open this email"><x-icon name="sms" class="size-4.5" /> SMS non-openers</button></form>
        @endif
        <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}">@csrf<button class="btn-secondary">Duplicate</button></form>
        @endunless
    </x-slot:actions>
</x-page-header>

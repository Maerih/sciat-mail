<x-layouts.app title="SMS campaigns">
    <x-page-header title="SMS campaigns" subtitle="Bulk text messages to your lists, with delivery reports.">
        <x-slot:actions>
            <a href="{{ route('sms.inbox') }}" class="btn-secondary"><x-icon name="forum" class="size-4.5" /> Inbox</a>
            <form method="POST" action="{{ route('sms.campaigns.store') }}">@csrf<button class="btn-primary"><x-icon name="add" /> New SMS campaign</button></form>
        </x-slot:actions>
    </x-page-header>
    <x-hero icon="sms" title="Reach people instantly by SMS" text="Send personalised text messages to thousands of contacts, follow up on unopened emails, and chat with customers who reply."
        :stats="[['send', number_format($stats['sent']), 'sent this month'], ['done_all', number_format($stats['delivered']), 'delivered'], ['forum', number_format($stats['replies']), 'replies']]"
        :actions="[['sms', 'Bulk SMS', 'Text a whole list or segment with merge tags and scheduling.', route('sms.campaigns.index')],
                   ['mark_email_unread', 'Email-to-SMS', 'Text the people who did not open an email campaign.', route('campaigns.index')],
                   ['forum', 'Two-way SMS', 'Read replies and answer them; STOP replies opt people out.', route('sms.inbox')]]" />

    @unless ($hasProvider)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <span class="flex items-center gap-2"><x-icon name="info" class="size-5" /> Connect an SMS provider before sending.</span>
            <a href="{{ route('sms.providers.index') }}" class="btn-primary btn-sm">Add provider</a>
        </div>
    @endunless

    <div class="card">
        @if ($campaigns->isEmpty())
            <x-empty title="No SMS campaigns yet" icon="sms">Write a message, pick a list and send — delivery reports come back automatically.</x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Campaign</th><th>Status</th><th>Provider</th><th>Scheduled / sent</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td><a href="{{ $campaign->isEditable() ? route('sms.campaigns.edit', $campaign) : route('sms.campaigns.show', $campaign) }}" class="font-medium hover:text-brand-600">{{ $campaign->name }}</a>
                                    <p class="max-w-md truncate text-xs text-[var(--muted)]">{{ $campaign->message ?: 'No message yet' }}</p></td>
                                <td><x-status-badge :status="$campaign->status" /></td>
                                <td class="text-[var(--muted)]">{{ $campaign->provider?->name ?? 'Default' }}</td>
                                <td class="whitespace-nowrap text-[var(--muted)]">{{ ($campaign->started_at ?? $campaign->scheduled_at)?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ $campaign->isEditable() ? route('sms.campaigns.edit', $campaign) : route('sms.campaigns.show', $campaign) }}" class="btn-ghost btn-sm">{{ $campaign->isEditable() ? 'Edit' : 'Report' }}</a>
                                    @unless (in_array($campaign->status, ['queued', 'sending']))<x-confirm-delete :action="route('sms.campaigns.destroy', $campaign)" />@endunless
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

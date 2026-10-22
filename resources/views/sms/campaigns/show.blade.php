<x-layouts.app :title="$campaign->name">
    <x-page-header :title="$campaign->name" :subtitle="'Bulk SMS · '.($campaign->provider?->name ?? 'Default provider')" :back="route('sms.campaigns.index')">
        <x-slot:actions>
            <x-status-badge :status="$campaign->status" />
            @if ($campaign->status === 'sending')<form method="POST" action="{{ route('sms.campaigns.pause', $campaign) }}">@csrf<button class="btn-secondary">Pause</button></form>@endif
            @if ($campaign->status === 'paused')<form method="POST" action="{{ route('sms.campaigns.resume', $campaign) }}">@csrf<button class="btn-primary">Resume</button></form>@endif
            @if (in_array($campaign->status, ['scheduled', 'queued', 'sending', 'paused']))
                <form method="POST" action="{{ route('sms.campaigns.cancel', $campaign) }}" onsubmit="return confirm('Cancel? Messages not yet sent will not go out.')">@csrf<button class="btn-ghost text-red-600">Cancel</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($campaign->last_error)<div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ $campaign->last_error }}</div>@endif
    @if (in_array($campaign->status, ['queued', 'sending', 'paused']))
        <div class="card card-body mb-6" x-data x-init="@if ($campaign->status !== 'paused') setTimeout(() => location.reload(), 8000) @endif">
            <div class="flex justify-between text-sm"><span class="font-medium">{{ $campaign->status === 'paused' ? 'Paused' : 'Sending' }}</span><span class="tabular-nums text-[var(--muted)]">{{ $stats['progress'] }}%</span></div>
            <div class="mt-2 h-2.5 rounded-full bg-[var(--surface-2)]"><div class="h-2.5 rounded-full bg-brand-500" style="width: {{ $stats['progress'] }}%"></div></div>
        </div>
    @elseif ($campaign->status === 'scheduled')
        <div class="card card-body mb-6 text-sm">Scheduled for <strong>{{ $campaign->scheduled_at->setTimezone($campaign->workspace->timezone)->format('D j M Y, H:i') }}</strong> ({{ $campaign->workspace->timezone }}).</div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['group', 'Recipients', number_format($stats['total']), 'bg-blue-50 text-blue-600'],
            ['send', 'Sent', number_format($stats['sent']), 'bg-brand-50 text-brand-600'],
            ['done_all', 'Delivered', number_format($stats['delivered']), 'bg-emerald-50 text-emerald-600'],
            ['error', 'Failed', number_format($stats['failed']), 'bg-red-50 text-red-600'],
            ['percent', 'Delivery rate', $stats['delivery_rate'].'%', 'bg-violet-50 text-violet-600'],
            ['person_off', 'Opted out', number_format($optedOut), 'bg-amber-50 text-amber-600'],
        ] as [$icon, $label, $value, $tile])
            <div class="card card-body !p-4"><span class="icon-tile size-9 {{ $tile }}"><x-icon :name="$icon" class="size-5" /></span>
                <div class="stat-label mt-3">{{ $label }}</div><div class="mt-1 text-[22px] leading-none font-bold tabular-nums">{{ $value }}</div></div>
        @endforeach
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_340px]">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Message log</h2><span class="text-xs text-[var(--muted)]">{{ number_format($stats['parts']) }} SMS parts billed</span></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Phone</th><th>Status</th><th>Parts</th><th>Sent</th><th>Detail</th></tr></thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td class="font-mono text-xs"><a href="{{ route('sms.inbox', ['phone' => $message->phone]) }}" class="hover:text-brand-600">{{ $message->phone }}</a></td>
                                <td><span @class(['badge', 'badge-green' => $message->status === 'delivered', 'badge-blue' => $message->status === 'sent', 'badge-gray' => $message->status === 'queued', 'badge-red' => $message->status === 'failed'])>{{ ucfirst($message->status) }}</span></td>
                                <td class="tabular-nums">{{ $message->parts }}</td>
                                <td class="whitespace-nowrap text-[var(--muted)]">{{ $message->sent_at?->diffForHumans() ?? '—' }}</td>
                                <td class="max-w-xs truncate text-xs text-[var(--muted)]" title="{{ $message->error }}">{{ $message->error }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-[var(--muted)]">No messages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $messages->links() }}
        </div>
        <div class="card card-body space-y-3">
            <h2 class="card-title">Message</h2>
            <div class="rounded-2xl rounded-bl-sm bg-[var(--surface-2)] px-4 py-3 text-sm whitespace-pre-line">{{ $campaign->fullMessage() }}</div>
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-[var(--muted)]">Audience</dt><dd>{{ \App\Models\SmsCampaign::AUDIENCES[$campaign->audienceValue('type', 'list')] }}</dd></div>
                <div class="flex justify-between"><dt class="text-[var(--muted)]">Sender ID</dt><dd>{{ $campaign->sender_id ?: ($campaign->provider?->sender_id ?: 'Provider default') }}</dd></div>
                <div class="flex justify-between"><dt class="text-[var(--muted)]">Started</dt><dd>{{ $campaign->started_at?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-[var(--muted)]">Finished</dt><dd>{{ $campaign->finished_at?->setTimezone($campaign->workspace->timezone)->format('M j, H:i') ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>
</x-layouts.app>

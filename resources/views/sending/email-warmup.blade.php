<x-layouts.app title="Email warmup">
    <x-page-header title="Email warmup" subtitle="Build sender reputation automatically before and between campaigns." />
    @php $active = $servers->filter(fn ($s) => $s->emailWarmup('enabled')); @endphp
    <x-banner icon="forward_to_inbox" title="Warm up with real engagement" text="Each day the platform sends a small, growing number of natural emails from your sending servers to seed inboxes you own. It opens them, marks them important and moves any that land in spam back to the inbox — the signals Gmail, Outlook and Yahoo use to trust a sender."
        :stats="[['dns', $active->count().' servers warming'], ['inbox', $mailboxes->count().' seed inboxes'], ['schedule', 'Sent between 08:00 and 20:00, checked every 15 min']]" />

    <div class="mb-6 card">
        <div class="card-header"><div><h2 class="card-title">Inbox placement — last 14 days</h2><p class="text-xs text-[var(--muted)]">Where warmup emails landed in your seed inboxes</p></div></div>
        <div class="card-body"><div class="h-56"><canvas id="warmup-chart"></canvas></div></div>
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-[1fr_400px]">
        <div class="space-y-6">
            @forelse ($servers as $server)
                @php $s = $stats[$server->id]; @endphp
                <form method="POST" action="{{ route('sending.email-warmup.servers.update', $server) }}" class="card" x-data="{ on: @js((bool) $server->emailWarmup('enabled')) }">
                    @csrf @method('PUT')
                    <div class="card-header">
                        <div><h2 class="card-title">{{ $server->name }} <span class="ml-1 text-xs font-normal text-[var(--muted)]">{{ $server->typeLabel() }}</span></h2>
                            <p class="text-xs text-[var(--muted)]">@if ($server->emailWarmup('enabled'))Day {{ $server->emailWarmupDay() }} · {{ $server->emailWarmupTarget() }} warmup emails today @else Not warming up @endif</p></div>
                        @if ($server->emailWarmup('enabled'))<span @class(['badge', 'badge-green' => $s['inbox_rate'] >= 85, 'badge-yellow' => $s['inbox_rate'] < 85 && $s['inbox_rate'] >= 60, 'badge-red' => $s['inbox_rate'] < 60 && ($s['inbox'] + $s['spam']) > 0, 'badge-gray' => ($s['inbox'] + $s['spam']) === 0])>{{ ($s['inbox'] + $s['spam']) ? $s['inbox_rate'].'% inbox' : 'No results yet' }}</span>@endif
                    </div>
                    <div class="card-body space-y-4">
                        @if ($server->emailWarmup('enabled'))
                            <div class="grid grid-cols-4 gap-3 text-center">
                                @foreach ([['Sent', $s['sent']], ['Inbox', $s['inbox']], ['Spam (rescued)', $s['spam']], ['Missing', $s['missing']]] as [$label, $value])
                                    <div class="rounded-lg bg-[var(--surface-2)] p-2"><div class="text-lg font-bold tabular-nums">{{ number_format($value) }}</div><div class="text-[11px] text-[var(--muted)]">{{ $label }}</div></div>
                                @endforeach
                            </div>
                        @endif
                        <x-toggle name="enabled" label="Warm up this server" help="Starts with 4 emails a day and adds 3 a day until the maximum." :checked="(bool) $server->emailWarmup('enabled')" x-model="on" />
                        <div x-show="on" x-cloak class="grid gap-4 sm:grid-cols-2">
                            <x-field name="from_email" type="email" label="Send warmup emails from" :value="$server->emailWarmup('from_email', $server->default_from_email)" help="An address on your authenticated sending domain." />
                            <x-field name="daily_max" type="number" label="Maximum per day" :value="$server->emailWarmup('daily_max', 40)" min="5" max="500" />
                        </div>
                        <div class="flex justify-end"><button class="btn-secondary btn-sm">Save</button></div>
                    </div>
                </form>
            @empty
                <div class="card"><x-empty title="No sending servers" icon="dns">Add a sending server first.</x-empty></div>
            @endforelse

            @if ($recent->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Recent warmup emails</h2></div>
                    <table class="table">
                        <thead><tr><th>Subject</th><th>Server → inbox</th><th>Placement</th><th>Sent</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $email)
                                <tr>
                                    <td class="max-w-xs truncate">{{ $email->subject }}</td>
                                    <td class="text-xs text-[var(--muted)]">{{ $email->server?->name }} → {{ $email->mailbox?->email }}</td>
                                    <td><span @class(['badge', 'badge-green' => $email->placement === 'inbox', 'badge-yellow' => $email->placement === 'spam', 'badge-red' => in_array($email->placement, ['missing', 'failed']), 'badge-gray' => $email->placement === 'pending'])>{{ $email->placement === 'spam' ? 'Spam → rescued' : ucfirst($email->placement) }}</span></td>
                                    <td class="whitespace-nowrap text-[var(--muted)]">{{ $email->sent_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Seed inboxes</h2><p class="text-xs text-[var(--muted)]">Mailboxes you own that receive warmup emails</p></div></div>
                @forelse ($mailboxes as $mailbox)
                    <div class="flex items-center justify-between gap-2 border-b border-[var(--line)] px-6 py-3 text-sm">
                        <div class="min-w-0"><p class="truncate font-medium">{{ $mailbox->email }}</p>
                            <p @class(['text-xs', 'text-red-600' => $mailbox->last_error, 'text-[var(--muted)]' => ! $mailbox->last_error])>{{ $mailbox->last_error ?: number_format($mailbox->received).' received · '.($mailbox->last_checked_at?->diffForHumans() ?? 'not checked yet') }}</p></div>
                        <div class="flex shrink-0">
                            <form method="POST" action="{{ route('sending.email-warmup.mailboxes.test', $mailbox) }}">@csrf<button class="btn-ghost btn-sm">Test</button></form>
                            <x-confirm-delete :action="route('sending.email-warmup.mailboxes.destroy', $mailbox)" />
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-4 text-sm text-[var(--muted)]">No seed inboxes yet. Add 3–10 mailboxes at different providers (Gmail, Outlook, Yahoo, your own domain) for the best signal.</p>
                @endforelse
                <form method="POST" action="{{ route('sending.email-warmup.mailboxes.store') }}" class="space-y-3 border-t border-[var(--line)] p-6" x-data="{ preset(h, p, e) { $refs.host.value = h; $refs.port.value = p; $refs.enc.value = e } }">
                    @csrf
                    <h3 class="text-sm font-semibold">Add a seed inbox</h3>
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <button type="button" class="btn-secondary btn-sm" @click="preset('imap.gmail.com', 993, 'ssl')">Gmail</button>
                        <button type="button" class="btn-secondary btn-sm" @click="preset('outlook.office365.com', 993, 'ssl')">Outlook</button>
                        <button type="button" class="btn-secondary btn-sm" @click="preset('imap.mail.yahoo.com', 993, 'ssl')">Yahoo</button>
                        <button type="button" class="btn-secondary btn-sm" @click="preset('imap.zoho.com', 993, 'ssl')">Zoho</button>
                    </div>
                    <x-field name="email" type="email" label="Email address" required />
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2"><label class="label">IMAP host</label><input name="host" x-ref="host" class="input" required value="{{ old('host') }}"></div>
                        <div><label class="label">Port</label><input name="port" x-ref="port" type="number" class="input" value="{{ old('port', 993) }}" required></div>
                    </div>
                    <div><label class="label">Encryption</label><select name="encryption" x-ref="enc" class="input"><option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none">None</option></select></div>
                    <x-field name="username" label="Username" required autocomplete="off" />
                    <x-field name="password" type="password" label="Password / app password" required autocomplete="new-password" help="Gmail, Yahoo and Outlook need an app password with IMAP enabled." />
                    <button class="btn-primary w-full"><x-icon name="add" class="size-4.5" /> Add inbox</button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const data = @js($chart);
            new Chart(document.getElementById('warmup-chart'), {
                type: 'bar',
                data: { labels: data.map(d => d.day), datasets: [
                    { label: 'Inbox', data: data.map(d => d.inbox), backgroundColor: themeColor('--color-brand-500'), borderRadius: 4, stack: 's' },
                    { label: 'Spam (rescued)', data: data.map(d => d.spam), backgroundColor: '#fbbf24', borderRadius: 4, stack: 's' },
                    { label: 'Sent', data: data.map(d => d.sent), type: 'line', borderColor: '#60a5fa', backgroundColor: 'transparent', tension: 0.35, pointRadius: 0 },
                ] },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
            });
        </script>
    @endpush
</x-layouts.app>

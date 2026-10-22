<x-layouts.app title="Sending health">
    <x-settings-frame :tab="$tab">
        <div class="card">
            <div class="card-header">
                <div><h2 class="card-title">Client health</h2><p class="text-xs text-[var(--muted)]">Hard bounces and complaints over the last {{ $config['health_window_days'] }} days. Checked every 15 minutes.</p></div>
                <span class="badge-{{ ['off' => 'gray', 'monitor' => 'blue', 'enforce' => 'green'][$config['health_mode']] }}">{{ \App\Services\SendingHealth::MODES[$config['health_mode']] }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Client</th><th class="text-right">Sent</th><th class="text-right">Hard bounces</th><th class="text-right">Complaints</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($workspaces as $row)
                            @php $w = $row['workspace']; $h = $row['health']; @endphp
                            <tr>
                                <td class="font-medium">{{ $w->name }}</td>
                                <td class="text-right tabular-nums">{{ number_format($h['sent']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($h['bounces']) }} <span class="text-xs text-[var(--muted)]">({{ $h['bounce_rate'] }}%)</span></td>
                                <td class="text-right tabular-nums">{{ number_format($h['complaints']) }} <span class="text-xs text-[var(--muted)]">({{ $h['complaint_rate'] }}%)</span></td>
                                <td>
                                    @if ($w->isSuspended())
                                        <span class="badge-red" title="{{ $w->suspension_reason }}"><x-icon name="block" class="size-3.5" /> Suspended</span>
                                        <p class="mt-1 text-xs text-[var(--muted)]">{{ $w->suspension_reason }} · {{ $w->sending_suspended_at->diffForHumans() }}</p>
                                    @elseif ($h['level'] === 'suspend')
                                        <span class="badge-red">Over limit</span><p class="mt-1 text-xs text-[var(--muted)]">{{ $h['reason'] }}</p>
                                    @elseif ($h['level'] === 'warn')
                                        <span class="badge-yellow">Warning</span><p class="mt-1 text-xs text-[var(--muted)]">{{ $h['reason'] }}</p>
                                    @elseif ($h['sent'] < $config['health_min_sent'])
                                        <span class="badge-gray">Not enough data</span>
                                    @else
                                        <span class="badge-green">Healthy</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($w->isSuspended())
                                        <form method="POST" action="{{ route('settings.health.lift', $w) }}" onsubmit="return confirm('Resume sending for {{ e($w->name) }}? Make sure the list problem has been cleaned up first.')">
                                            @csrf <button class="btn-secondary btn-sm"><x-icon name="play_arrow" class="size-4" /> Resume sending</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-[var(--muted)]">No clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.health') }}" class="space-y-6" x-data="{ mode: @js(old('health_mode', $config['health_mode'])) }">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Protection mode</h2><p class="text-xs text-[var(--muted)]">High bounce or complaint rates damage the reputation of your sending servers and IPs for every client.</p></div></div>
                <div class="card-body grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        'off' => ['Off', 'No checks.'],
                        'monitor' => ['Monitor only', 'Flag clients over the limits here and on their campaigns.'],
                        'enforce' => ['Monitor and enforce', 'Suspend the client and pause running campaigns when a limit is crossed.'],
                    ] as $value => [$label, $text])
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3.5" :class="mode === @js($value) ? 'border-brand-500 bg-brand-50' : 'border-[var(--line)]'">
                            <input type="radio" name="health_mode" value="{{ $value }}" x-model="mode" class="mt-0.5 text-brand-600">
                            <span><span class="block text-sm font-medium">{{ $label }}</span><span class="block text-xs text-[var(--muted)]">{{ $text }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Limits</h2><p class="text-xs text-[var(--muted)]">Gmail and Yahoo expect complaints under 0.3% and most providers expect hard bounces under 5%.</p></div></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-field name="health_bounce_warn" type="number" step="0.1" label="Hard bounce warning (%)" :value="$config['health_bounce_warn']" required />
                    <x-field name="health_bounce_suspend" type="number" step="0.1" label="Hard bounce suspend (%)" :value="$config['health_bounce_suspend']" required />
                    <x-field name="health_complaint_warn" type="number" step="0.01" label="Complaint warning (%)" :value="$config['health_complaint_warn']" required />
                    <x-field name="health_complaint_suspend" type="number" step="0.01" label="Complaint suspend (%)" :value="$config['health_complaint_suspend']" required />
                    <x-field name="health_window_days" type="number" label="Rolling window (days)" :value="$config['health_window_days']" required />
                    <x-field name="health_min_sent" type="number" label="Minimum emails before judging" :value="$config['health_min_sent']" help="Clients with fewer sends in the window are not rated, so a handful of bounces can't suspend a small send." required />
                </div>
            </div>
            <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" class="size-4.5" /> Save settings</button></div>
        </form>
    </x-settings-frame>
</x-layouts.app>

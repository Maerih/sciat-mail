<x-layouts.app title="Cron & queue">
    <x-settings-frame :tab="$tab">
        @php
            $fresh = fn ($t) => $t && $t->gt(now()->subMinutes(3));
        @endphp
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([['Scheduler (cron)', $scheduler, 'schedule'], ['Queue worker', $workerBeat, 'memory']] as [$label, $beat, $icon])
                <div class="card card-body">
                    <div class="flex items-center gap-3">
                        <span @class(['icon-tile', 'bg-emerald-50 text-emerald-600' => $fresh($beat), 'bg-red-50 text-red-600' => ! $fresh($beat)])><x-icon :name="$icon" class="size-5" /></span>
                        <div><div class="stat-label">{{ $label }}</div>
                            <div @class(['text-sm font-semibold', 'text-emerald-600' => $fresh($beat), 'text-red-600' => ! $fresh($beat)])>{{ $fresh($beat) ? 'Running' : 'Not running' }}</div>
                            <div class="text-xs text-[var(--muted)]">{{ $beat ? 'Last seen '.$beat->diffForHumans() : 'Never seen' }}</div></div>
                    </div>
                </div>
            @endforeach
            <div class="card card-body">
                <div class="flex items-center gap-3">
                    <span class="icon-tile bg-blue-50 text-blue-600"><x-icon name="stacks" class="size-5" /></span>
                    <div><div class="stat-label">Queue</div>
                        <div class="text-sm font-semibold">{{ $queueSize === null ? 'Unknown' : number_format($queueSize).' waiting' }}</div>
                        <div @class(['text-xs', 'text-red-600' => $failed > 0, 'text-[var(--muted)]' => ! $failed])>{{ number_format($failed) }} failed</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Cron command</h2><p class="text-xs text-[var(--muted)]">Add to the server's crontab so scheduled campaigns, automations, bounce checks and warmup run every minute.</p></div></div>
            <div class="card-body space-y-4" x-data="copy(@js($command))">
                <div class="flex gap-2"><div class="code flex-1">{{ $command }}</div><button type="button" class="btn-secondary" @click="copy()" x-text="copied ? 'Copied' : 'Copy'"></button></div>
                <div>
                    <span class="label">Queue worker (keep running with Supervisor or systemd)</span>
                    <div class="code">{{ $worker }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h2 class="card-title">Failed jobs</h2><p class="text-xs text-[var(--muted)]">Background tasks that kept failing after their retries.</p></div>
                @if ($failed)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('settings.cron') }}">@csrf<input type="hidden" name="action" value="retry_failed"><button class="btn-secondary btn-sm">Retry all</button></form>
                        <form method="POST" action="{{ route('settings.cron') }}" onsubmit="return confirm('Delete all failed jobs?')">@csrf<input type="hidden" name="action" value="flush_failed"><button class="btn-ghost btn-sm text-red-600">Delete all</button></form>
                    </div>
                @endif
            </div>
            @forelse ($recentFailed as $job)
                <div class="border-b border-[var(--line)] px-6 py-3 last:border-0">
                    <div class="flex justify-between text-sm"><span class="font-medium">{{ json_decode($job->payload)->displayName ?? 'Job' }}</span><span class="text-xs text-[var(--muted)]">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</span></div>
                    <div class="mt-1 truncate font-mono text-xs text-red-600">{{ \Illuminate\Support\Str::before($job->exception, "\n") }}</div>
                </div>
            @empty
                <p class="px-6 py-6 text-sm text-[var(--muted)]">No failed jobs. 🎉</p>
            @endforelse
        </div>
    </x-settings-frame>
</x-layouts.app>

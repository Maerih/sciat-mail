<x-layouts.app title="Maintenance">
    <x-settings-frame :tab="$tab">
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="system_update" class="size-5 text-[var(--muted)]" /><div><h2 class="card-title">Updates</h2><p class="text-xs text-[var(--muted)]">Installed version {{ $version ?? 'development copy' }}</p></div></div>
                @if ($pending)<span class="badge-yellow">{{ count($pending) }} pending</span>@else<span class="badge-green">Up to date</span>@endif</div>
            <div class="card-body space-y-4">
                @if ($pending)
                    <p class="text-sm">A new version was uploaded and needs these database changes:</p>
                    <ul class="code space-y-0.5">@foreach ($pending as $migration)<li>{{ $migration }}</li>@endforeach</ul>
                    <form method="POST" action="{{ route('settings.maintenance') }}" onsubmit="return confirm('Apply the database changes now? A backup is a good idea first.')">@csrf
                        <input type="hidden" name="action" value="migrate"><button class="btn-primary"><x-icon name="upgrade" class="size-4.5" /> Apply update</button></form>
                @else
                    <p class="text-sm text-[var(--muted)]">The database matches the installed code.</p>
                @endif
                <details class="text-sm">
                    <summary class="cursor-pointer font-medium">How to install an update on shared hosting</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-[var(--muted)]">
                        <li>Create a backup below and download it.</li>
                        <li>Upload the new package zip (built with <code>php artisan app:package</code>) and extract it over the existing folder. It contains no <code>.env</code>, so your settings and uploads are kept.</li>
                        <li>Come back here and click <strong>Apply update</strong>.</li>
                    </ol>
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="backup" class="size-5 text-[var(--muted)]" /><div><h2 class="card-title">Database backups</h2><p class="text-xs text-[var(--muted)]">Gzip SQL files — import them with phpMyAdmin to restore</p></div></div>
                <form method="POST" action="{{ route('settings.maintenance') }}">@csrf<input type="hidden" name="action" value="backup"><button class="btn-secondary btn-sm"><x-icon name="add" class="size-4" /> Back up now</button></form></div>
            @forelse ($backups as $backup)
                <div class="flex items-center justify-between gap-3 border-b border-[var(--line)] px-6 py-3 text-sm last:border-0">
                    <div class="min-w-0"><p class="truncate font-mono text-xs">{{ $backup['name'] }}</p><p class="text-xs text-[var(--muted)]">{{ number_format($backup['size'] / 1024) }} KB · {{ \Illuminate\Support\Carbon::createFromTimestamp($backup['created'])->diffForHumans() }}</p></div>
                    <div class="flex shrink-0 gap-1">
                        <a href="{{ route('settings.backups.download', $backup['name']) }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" /> Download</a>
                        <x-confirm-delete :action="route('settings.backups.delete', $backup['name'])" message="Delete this backup?" />
                    </div>
                </div>
            @empty
                <p class="px-6 py-6 text-center text-sm text-[var(--muted)]">No backups yet.</p>
            @endforelse
            <form method="POST" action="{{ route('settings.maintenance') }}" class="flex flex-wrap items-end gap-4 border-t border-[var(--line)] px-6 py-4">
                @csrf <input type="hidden" name="action" value="backup_settings">
                <div class="flex-1"><x-toggle name="backups_enabled" label="Daily automatic backup" help="Runs at 02:30 from the cron job. Also keep copies off the server — download them or use your host's backups." :checked="$backupsEnabled" /></div>
                <div class="w-32"><x-field name="backup_keep" type="number" label="Keep newest" :value="$backupKeep" min="1" max="60" /></div>
                <button class="btn-secondary">Save</button>
            </form>
        </div>

        <div class="card card-body flex flex-wrap items-center justify-between gap-4">
            <div><h2 class="card-title">Caches</h2><p class="text-sm text-[var(--muted)]">Clear compiled views, config and route caches — useful after uploading files by hand.</p></div>
            <form method="POST" action="{{ route('settings.maintenance') }}">@csrf<input type="hidden" name="action" value="clear_cache"><button class="btn-secondary"><x-icon name="cleaning_services" class="size-4.5" /> Clear caches</button></form>
        </div>
    </x-settings-frame>
</x-layouts.app>

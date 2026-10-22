<x-layouts.app title="System info">
    <x-settings-frame :tab="$tab">
        @foreach ($rows as $section => $items)
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ $section }}</h2></div>
                <dl class="divide-y divide-[var(--line)]">
                    @foreach ($items as $label => $value)
                        <div class="flex justify-between gap-4 px-6 py-3 text-sm"><dt class="text-[var(--muted)]">{{ $label }}</dt><dd class="truncate text-right font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @endforeach
        <div class="card">
            <div class="card-header"><h2 class="card-title">PHP extensions</h2></div>
            <div class="card-body flex flex-wrap gap-2">
                @foreach ($extensions as $ext => $ok)
                    <span class="{{ $ok ? 'badge-green' : 'badge-red' }}"><x-icon :name="$ok ? 'check' : 'close'" class="size-3.5" /> {{ $ext }}</span>
                @endforeach
                <span class="{{ $storageWritable ? 'badge-green' : 'badge-red' }}"><x-icon :name="$storageWritable ? 'check' : 'close'" class="size-3.5" /> storage writable</span>
            </div>
        </div>
    </x-settings-frame>
</x-layouts.app>

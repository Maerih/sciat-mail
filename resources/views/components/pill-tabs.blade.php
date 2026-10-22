@props(['items'])
{{-- Icon pill navigation for sub-pages: each item is [label, url, active, icon, ?count] --}}
<div class="mb-6 flex gap-1.5 overflow-x-auto border-b border-[var(--line)] pb-3">
    @foreach ($items as $item)
        @php [$label, $url, $active, $icon] = $item; $count = $item[4] ?? null; @endphp
        <a href="{{ $url }}" @class([
            'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition',
            'border-brand-500/60 bg-brand-50 text-brand-600' => $active,
            'border-transparent text-[var(--muted)] hover:bg-[var(--surface-2)] hover:text-[var(--text)]' => ! $active,
        ])><x-icon :name="$icon" class="size-4.5" /> {{ $label }}@if ($count !== null)<span class="rounded bg-[var(--surface-2)] px-1.5 text-[11px] tabular-nums">{{ $count }}</span>@endif</a>
    @endforeach
</div>

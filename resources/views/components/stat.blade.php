@props(['label', 'value', 'hint' => null, 'tone' => 'gray', 'icon' => null])
@php
    $tile = match ($tone) { 'green' => 'bg-emerald-50 text-emerald-600', 'red' => 'bg-red-50 text-red-600', 'amber' => 'bg-amber-50 text-amber-600', default => 'bg-blue-50 text-blue-600' };
@endphp
<div class="card card-body">
    <div class="flex items-start gap-3.5">
        @if ($icon)
            <span class="icon-tile {{ $tile }}"><x-icon :name="$icon" class="size-5" /></span>
        @endif
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div @class(['mt-1 text-[26px] leading-tight font-bold tabular-nums', 'text-red-600' => $tone === 'red', 'text-amber-600' => $tone === 'amber'])>{{ $value }}</div>
            @if ($hint)
                <div class="mt-1 text-xs text-[var(--muted)]">{{ $hint }}</div>
            @endif
        </div>
    </div>
</div>

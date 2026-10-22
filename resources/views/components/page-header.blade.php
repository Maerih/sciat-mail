@props(['title', 'subtitle' => null, 'back' => null, 'icon' => null])
<div class="mb-7 flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-3.5">
        @if ($icon)
            <span class="icon-tile mt-0.5 bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5.5" /></span>
        @endif
        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1 text-xs font-medium text-[var(--muted)] hover:text-[var(--text)]"><x-icon name="arrow_back" class="size-3.5" /> Back</a>
            @endif
            <h1 class="truncate text-[26px] leading-tight font-bold tracking-tight">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1.5 text-sm text-[var(--muted)]">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>

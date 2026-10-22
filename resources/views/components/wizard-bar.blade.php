@props(['icon', 'title', 'text' => null])
{{-- Sticky footer for multi-step forms: a hint on the left, the step's buttons on the right --}}
<div class="sticky bottom-0 z-10 -mx-4 mt-8 border-t border-[var(--line)] bg-[var(--surface)]/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="icon-tile size-9 bg-brand-50 text-brand-600"><x-icon :name="$icon" class="size-5" /></span>
            <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $title }}</p>@if ($text)<p class="truncate text-xs text-[var(--muted)]">{{ $text }}</p>@endif</div>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    </div>
</div>

@props(['icon', 'title', 'text', 'stats' => []])
{{-- Wide intro card for section pages: headline, explanation, a line of counters and an illustration --}}
<div {{ $attributes->merge(['class' => 'card relative mb-6 overflow-hidden']) }}>
    <div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-gradient-to-l from-brand-500/10 to-transparent"></div>
    <div class="relative flex items-center gap-6 px-7 py-6">
        <div class="min-w-0 flex-1">
            <h2 class="text-[22px] leading-tight font-bold tracking-tight">{{ $title }}</h2>
            <p class="mt-2 max-w-2xl text-sm text-[var(--muted)]">{{ $text }}</p>
            @if ($stats)
                <div class="mt-3.5 flex flex-wrap gap-x-5 gap-y-1.5 text-[13px] text-[var(--muted)]">
                    @foreach ($stats as [$statIcon, $label])
                        <span class="flex items-center gap-1.5"><x-icon :name="$statIcon" class="size-4" /> {!! $label !!}</span>
                    @endforeach
                </div>
            @endif
            @if ($slot->isNotEmpty())<div class="mt-4 flex flex-wrap gap-2">{{ $slot }}</div>@endif
        </div>
        <div class="relative hidden shrink-0 sm:block" aria-hidden="true">
            <span class="absolute inset-0 -m-4 rounded-full border border-dashed border-brand-500/25"></span>
            <span class="absolute inset-0 -m-9 rounded-full border border-brand-500/10"></span>
            <span class="relative flex size-16 items-center justify-center rounded-2xl border border-brand-500/30 bg-brand-50 text-brand-600 shadow-[0_0_40px_-8px] shadow-brand-500/40">
                <x-icon :name="$icon" class="size-8" />
            </span>
        </div>
    </div>
</div>

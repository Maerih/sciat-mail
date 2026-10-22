@props(['icon', 'title', 'text', 'stats' => [], 'actions' => []])
{{-- Section intro in the style of the demo: illustration, headline, counters and quick actions --}}
<div class="mb-6">
    <div class="flex flex-col items-center px-4 pt-4 pb-8 text-center">
        <div class="relative mb-5">
            <span class="absolute inset-0 -m-3 rounded-full bg-brand-500/10 blur-xl"></span>
            <span class="relative flex size-20 items-center justify-center rounded-full border border-brand-500/30 bg-brand-50 text-brand-600">
                <x-icon :name="$icon" class="size-10" />
            </span>
        </div>
        <h2 class="max-w-xl text-[30px] leading-tight font-bold tracking-tight">{{ $title }}</h2>
        <p class="mt-2.5 max-w-xl text-[15px] text-[var(--muted)]">{{ $text }}</p>
        @if ($stats)
            <div class="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-1 text-sm text-[var(--muted)]">
                @foreach ($stats as [$statIcon, $value, $label])
                    <span class="flex items-center gap-1.5"><x-icon :name="$statIcon" class="size-4.5" /><span class="font-semibold text-[var(--text)]">{{ $value }}</span> {{ $label }}</span>
                @endforeach
            </div>
        @endif
    </div>
    @if ($actions)
        <div class="grid gap-4 md:grid-cols-{{ min(3, count($actions)) }}">
            @foreach ($actions as [$actionIcon, $actionTitle, $actionText, $url])
                <a href="{{ $url }}" class="card card-body flex items-start gap-3.5 transition hover:border-brand-500/50">
                    <span class="icon-tile bg-[var(--surface-2)] text-[var(--muted)]"><x-icon :name="$actionIcon" class="size-5" /></span>
                    <span><span class="block text-sm font-semibold">{{ $actionTitle }}</span><span class="mt-0.5 block text-xs text-[var(--muted)]">{{ $actionText }}</span></span>
                </a>
            @endforeach
        </div>
    @endif
</div>

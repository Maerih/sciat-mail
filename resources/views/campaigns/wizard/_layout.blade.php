{{-- Shared wizard chrome: header, step cards and the step's intro --}}
@php
    $stepMeta = [
        'recipients' => ['group', 'Who gets this email', 'Choose your recipients', 'Select which lists and segments to target. Add several lists and exclude people with specific segments.'],
        'setup' => ['tune', 'Sender, subject & tracking', 'Campaign setup', 'Set the campaign name, subject line, sender details and tracking options.'],
        'content' => ['palette', 'Design the email', 'Design your email', 'Start from a template, build it with drag and drop, or write your own HTML.'],
        'variants' => ['science', 'Versions to compare', 'A/B test variants', 'Try different subject lines, senders or content on a test group, then send the winner to everyone else.'],
        'schedule' => ['schedule', 'When to send', 'Schedule delivery', 'Send right away or pick a date and time, and optionally limit sending to certain hours.'],
        'confirm' => ['rocket_launch', 'Review & launch', 'Review and launch', 'Check everything is ready, send yourself a test, then launch.'],
    ];
    $keys = array_keys($steps);
    $currentIndex = array_search($current, $keys);
    [$currentIcon, , $introTitle, $introText] = $stepMeta[$current];
@endphp
<x-page-header :title="$campaign->name" :subtitle="'Step '.($currentIndex + 1).' of '.count($steps).' — '.lcfirst($stepMeta[$current][1])" :back="route('campaigns.index')">
    <x-slot:actions>
        <span class="badge-gray">{{ $campaign->isAbTest() ? 'A/B test' : ($campaign->isPlain() ? 'Plain text' : 'HTML') }}</span>
        <a href="{{ route('campaigns.preview', $campaign) }}" target="_blank" class="btn-secondary"><x-icon name="visibility" class="size-4.5" /> Preview</a>
    </x-slot:actions>
</x-page-header>

<ol class="mb-8 flex items-center gap-2 overflow-x-auto pb-1">
    @foreach ($steps as $key => $label)
        @php $index = $loop->index; $done = $index < $currentIndex; $active = $key === $current; @endphp
        @if (! $loop->first)<li aria-hidden="true" class="shrink-0 text-[var(--muted)]"><x-icon name="arrow_forward" class="size-4" /></li>@endif
        <li class="min-w-0 flex-1">
            <a href="{{ route('campaigns.'.$key, $campaign) }}" @class([
                'flex items-center gap-3 rounded-xl border px-3 py-2.5 transition',
                'border-brand-500/60 bg-brand-50' => $active,
                'border-transparent hover:bg-[var(--surface-2)]' => ! $active,
            ])>
                <span @class([
                    'relative flex size-9 shrink-0 items-center justify-center rounded-full',
                    'bg-brand-600 text-[var(--on-brand)]' => $active,
                    'bg-brand-50 text-brand-600' => $done,
                    'bg-[var(--surface-2)] text-[var(--muted)]' => ! $active && ! $done,
                ])>
                    <x-icon :name="$stepMeta[$key][0]" class="size-5" />
                    @if ($done)<span class="absolute -right-0.5 -bottom-0.5 flex size-4 items-center justify-center rounded-full bg-brand-600 text-[var(--on-brand)] ring-2 ring-[var(--page)]"><x-icon name="check" class="size-3" /></span>@endif
                </span>
                <span class="hidden min-w-0 md:block">
                    <span @class(['block truncate text-sm font-semibold', 'text-brand-600' => $active, 'text-[var(--muted)]' => ! $active && ! $done])>{{ $label }}</span>
                    <span class="block truncate text-xs text-[var(--muted)]">{{ $stepMeta[$key][1] }}</span>
                </span>
            </a>
        </li>
    @endforeach
</ol>

<div class="mb-6 flex items-center justify-between gap-6">
    <div>
        <h2 class="text-[22px] font-bold tracking-tight">{{ $introTitle }}</h2>
        <p class="mt-1 max-w-2xl text-sm text-[var(--muted)]">{{ $introText }}</p>
    </div>
    <div class="relative hidden shrink-0 sm:block" aria-hidden="true">
        <span class="absolute inset-0 -m-3 rounded-2xl border border-dashed border-brand-500/25"></span>
        <span class="relative flex size-14 items-center justify-center rounded-2xl border border-brand-500/30 bg-brand-50 text-brand-600"><x-icon :name="$currentIcon" class="size-7" /></span>
    </div>
</div>

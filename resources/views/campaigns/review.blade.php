<x-layouts.app :title="'Email review · '.$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')
    @php
        $levels = collect($checks)->countBy('level');
        $tone = $score >= 85 ? ['text-emerald-600', 'Ready to send', 'stroke-emerald-500'] : ($score >= 60 ? ['text-amber-600', 'Worth a second look', 'stroke-amber-500'] : ['text-red-600', 'Needs work', 'stroke-red-500']);
        $meta = ['ok' => ['check_circle', 'text-emerald-600'], 'warning' => ['warning', 'text-amber-600'], 'error' => ['error', 'text-red-600']];
    @endphp
    <div class="grid items-start gap-6 lg:grid-cols-[320px_1fr]">
        <div class="space-y-6">
            <div class="card card-body text-center">
                <div class="relative mx-auto size-36">
                    <svg viewBox="0 0 36 36" class="size-full -rotate-90"><circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--surface-2)" stroke-width="3" />
                        <circle cx="18" cy="18" r="15.9" fill="none" class="{{ $tone[2] }}" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ $score }} 100" /></svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center"><span class="text-3xl font-bold">{{ $score }}</span><span class="text-xs text-[var(--muted)]">out of 100</span></div>
                </div>
                <p class="mt-3 font-semibold {{ $tone[0] }}">{{ $tone[1] }}</p>
                <p class="mt-1 text-xs text-[var(--muted)]">{{ $levels['ok'] ?? 0 }} passed · {{ $levels['warning'] ?? 0 }} warnings · {{ $levels['error'] ?? 0 }} problems</p>
            </div>
            <div class="card card-body space-y-3">
                <h2 class="card-title">Check links</h2>
                <p class="text-sm text-[var(--muted)]">Visits up to 25 links in the email and reports any that don't respond.</p>
                <a href="{{ route('campaigns.review', [$campaign, 'links' => 1]) }}" class="btn-secondary w-full"><x-icon name="link" class="size-4.5" /> {{ $checkedLinks ? 'Check again' : 'Check links now' }}</a>
            </div>
            @if (in_array($campaign->status, ['draft', 'scheduled', 'paused'], true))
                <a href="{{ route('campaigns.content', $campaign) }}" class="btn-primary w-full"><x-icon name="edit" class="size-4.5" /> Edit content</a>
            @endif
        </div>
        <div class="card">
            <div class="card-header"><div><h2 class="card-title">Content checks</h2><p class="text-xs text-[var(--muted)]">Subject, preheader, spam signals, links, images and size</p></div></div>
            @foreach ($checks as $check)
                <div class="flex items-start gap-3 border-b border-[var(--line)] px-6 py-3.5 last:border-0">
                    <x-icon :name="$meta[$check['level']][0]" class="mt-0.5 size-5 {{ $meta[$check['level']][1] }}" fill />
                    <div class="min-w-0"><p class="text-sm font-medium">{{ $check['label'] }}</p><p class="text-sm break-words text-[var(--muted)]">{{ $check['message'] }}</p></div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>

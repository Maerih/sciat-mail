<x-install-layout title="Server check" step="requirements">
    <div class="card">
        <div class="card-header"><div><h2 class="card-title">Server check</h2><p class="text-xs text-[var(--muted)]">Making sure your hosting can run the platform</p></div>
            @if ($passes)<span class="badge-green">Ready</span>@else<span class="badge-red">Action needed</span>@endif</div>
        @foreach ($checks as $check)
            <div class="flex items-start gap-3 border-b border-[var(--line)] px-6 py-2.5 last:border-0">
                <x-icon :name="$check['ok'] ? 'check_circle' : ($check['required'] ? 'cancel' : 'warning')" fill @class(['mt-0.5 size-5', 'text-emerald-600' => $check['ok'], 'text-red-600' => ! $check['ok'] && $check['required'], 'text-amber-600' => ! $check['ok'] && ! $check['required']]) />
                <div class="min-w-0 text-sm"><p>{{ $check['label'] }}@unless ($check['required'])<span class="ml-1 text-xs text-[var(--muted)]">(optional)</span>@endunless</p>
                    @unless ($check['ok'])<p class="text-xs text-[var(--muted)]">{{ $check['detail'] }}</p>@endunless</div>
            </div>
        @endforeach
    </div>
    <div class="mt-6 flex justify-end gap-2">
        <a href="{{ route('install.requirements') }}" class="btn-secondary"><x-icon name="refresh" class="size-4.5" /> Check again</a>
        @if ($passes)<a href="{{ route('install.database') }}" class="btn-primary">Continue <x-icon name="arrow_forward" class="size-4.5" /></a>@endif
    </div>
</x-install-layout>

@props(["tab", "tabs" => \App\Http\Controllers\SettingsController::TABS])
{{-- Settings hub frame: header + tab navigation; pages pass their content as $slot --}}
<x-page-header title="Settings" subtitle="Platform-wide configuration for your company and team." icon="settings" />
<div class="grid gap-6 lg:grid-cols-[220px_1fr]">
    <nav class="card h-fit p-2 lg:sticky lg:top-20">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ $key === 'ai' ? route('platform.ai') : route('settings.show', $key) }}" @class(['nav-link', 'active' => ($tab ?? '') === $key])>
                <x-icon :name="$icon" class="size-5" /> {{ $label }}
            </a>
        @endforeach
    </nav>
    <div class="min-w-0 space-y-6">{{ $slot }}</div>
</div>

@props(['title', 'step'])
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Install — {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    @php
        $steps = ['requirements' => ['Server check', 'fact_check'], 'database' => ['Database', 'database'], 'admin' => ['Administrator', 'admin_panel_settings'], 'done' => ['Finish', 'rocket_launch']];
        $keys = array_keys($steps);
    @endphp
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-8 flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-[var(--on-brand)]"><x-icon name="mail" class="size-6" /></span>
            <div><h1 class="text-lg font-bold">Installation</h1><p class="text-xs text-[var(--muted)]">Set up your email platform in a few steps</p></div>
        </div>
        <ol class="mb-8 grid grid-cols-4 gap-2">
            @foreach ($steps as $key => [$label, $icon])
                @php $done = array_search($key, $keys) < array_search($step, $keys); @endphp
                <li @class(['flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-medium', 'border-brand-500/60 bg-brand-50 text-brand-600' => $key === $step, 'border-[var(--line)] text-[var(--muted)]' => $key !== $step])>
                    <x-icon :name="$done ? 'check_circle' : $icon" class="size-4.5" /> <span class="hidden sm:inline">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
        {{ $slot }}
        <p class="mt-8 text-center text-xs text-[var(--muted)]">The installer switches itself off when it finishes.</p>
    </div>
</body>
</html>

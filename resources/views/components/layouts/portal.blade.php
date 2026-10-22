@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $portalWorkspace->company ?: $portalWorkspace->name }}</title>
    @include('partials.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss = \App\Services\Branding::css(\App\Services\Branding::clientColor($portalWorkspace)))<style>{!! $brandCss !!}</style>@endif
</head>
<body class="min-h-full font-sans text-[14px] antialiased">
    <header class="sticky top-0 z-20 border-b border-[var(--line)] bg-[var(--surface)]/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-2 px-4 py-3 sm:px-6">
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-3">
                @if ($logo = \App\Services\Branding::clientLogo($portalWorkspace))
                    <img src="{{ $logo }}" alt="" class="h-9 max-w-[10rem] object-contain">
                @endif
                <span class="font-semibold text-gray-900">{{ $portalWorkspace->company ?: $portalWorkspace->name }}</span>
            </a>
            <nav class="flex gap-5 text-sm">
                @foreach ([['Overview', 'portal.dashboard', 'portal.dashboard'], ['Campaigns', 'portal.campaigns', 'portal.campaign*'], ['Audience', 'portal.lists', 'portal.lists']] as [$label, $route, $pattern])
                    <a href="{{ route($route) }}" @class(['font-medium', 'text-brand-700' => request()->routeIs($pattern), 'text-gray-500 hover:text-gray-900' => ! request()->routeIs($pattern)])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <a href="{{ route('portal.account') }}" class="text-gray-600 hover:text-gray-900">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost btn-sm">Log out</button></form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @include('partials.flash')
        {{ $slot }}
    </main>
    <footer class="pb-8 text-center text-xs text-gray-400">Reports provided by {{ \App\Services\Branding::appName() }}</footer>
    @stack('scripts')
</body>
</html>

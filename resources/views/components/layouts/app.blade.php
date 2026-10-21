<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Services\Branding::appName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss = \App\Services\Branding::css(\App\Services\Branding::appColor()))<style>{!! $brandCss !!}</style>@endif
</head>
<body class="h-full font-sans text-gray-800 antialiased" x-data="{ nav: false }">
@php
    $nav = [
        ['Dashboard', 'dashboard', 'home', 'dashboard'],
        ['Campaigns', 'campaigns.index', 'megaphone', 'campaigns.*'],
        ['Automations', 'automations.index', 'bolt', 'automations.*'],
        ['Templates', 'templates.index', 'template', 'templates.*'],
        'Audience',
        ['Lists', 'lists.index', 'users', 'lists.*'],
        ['Signup forms', 'forms.index', 'form', 'forms.*'],
        'Sending',
        ['Sending servers', 'sending.servers.index', 'server', 'sending.servers.*'],
        ['Domains', 'sending.domains.index', 'globe', 'sending.domains.*'],
        ['Tracking domains', 'sending.tracking-domains.index', 'link', 'sending.tracking-domains.*'],
        ['IP warmup', 'sending.warmup.index', 'clock', 'sending.warmup.*'],
        ['Email verification', 'sending.verification.index', 'check', 'sending.verification.*'],
        ['Bounce handlers', 'sending.bounce-handlers.index', 'envelope', 'sending.bounce-handlers.*'],
        ['Blacklist', 'sending.blacklist.index', 'ban', 'sending.blacklist.*'],
        'Settings',
        ['Client settings', 'workspaces.edit', 'cog', 'workspaces.edit'],
        ['API & webhooks', 'api.settings', 'link', 'api.*'],
    ];
    if (auth()->user()->isAdmin()) {
        $nav[] = ['All clients', 'workspaces.index', 'building', 'workspaces.index'];
        $nav[] = ['Team', 'team.index', 'team', 'team.*'];
        $nav[] = ['Platform branding', 'platform.edit', 'template', 'platform.edit'];
        $nav[] = ['AI assistant', 'platform.ai', 'bolt', 'platform.ai'];
    }
@endphp

<div x-cloak x-show="nav" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" @click="nav = false"></div>

<aside :class="nav ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-gray-900 transition-transform lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center gap-2 px-5">
        @if ($platformLogo = \App\Services\Branding::appLogo())
            <img src="{{ $platformLogo }}" alt="" class="h-8 max-w-[10rem] object-contain">
        @else
            <div class="flex size-8 items-center justify-center rounded-lg bg-brand-500 text-white"><x-icon name="paper-airplane" class="size-4.5" /></div>
        @endif
        <span class="truncate text-base font-semibold text-white">{{ \App\Services\Branding::appName() }}</span>
    </div>

    @isset($currentWorkspace)
        <div class="px-3" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-lg bg-white/5 px-3 py-2 text-left hover:bg-white/10">
                <span class="min-w-0">
                    <span class="block text-[11px] font-medium tracking-wider text-gray-400 uppercase">Client</span>
                    <span class="block truncate text-sm font-semibold text-white">{{ $currentWorkspace->name }}</span>
                </span>
                <x-icon name="chevron-down" class="size-4 text-gray-400" />
            </button>
            <div x-cloak x-show="open" @click.outside="open = false" class="mt-1 max-h-72 overflow-y-auto rounded-lg bg-gray-800 p-1 shadow-lg">
                @foreach ($allWorkspaces as $ws)
                    <form method="POST" action="{{ route('workspaces.switch', $ws->uid) }}">
                        @csrf
                        <button class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-left text-sm text-gray-200 hover:bg-white/10">
                            <span class="truncate">{{ $ws->name }}</span>
                            @if ($ws->id === $currentWorkspace->id)<x-icon name="check" class="size-4 text-brand-100" />@endif
                        </button>
                    </form>
                @endforeach
                <a href="{{ route('workspaces.create') }}" class="mt-1 flex items-center gap-2 rounded-md border-t border-white/10 px-3 py-2 text-sm text-gray-300 hover:bg-white/10">
                    <x-icon name="plus" class="size-4" /> New client
                </a>
            </div>
        </div>
    @endisset

    <nav class="mt-2 flex-1 overflow-y-auto px-3 pb-4">
        @foreach ($nav as $item)
            @if (is_string($item))
                <div class="nav-section">{{ $item }}</div>
            @else
                <a href="{{ route($item[1]) }}" @class(['nav-link', 'active' => request()->routeIs($item[3])])>
                    <x-icon :name="$item[2]" /> {{ $item[0] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-white/5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-white">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-gray-400">{{ ucfirst(auth()->user()->role) }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg p-2 text-gray-400 hover:bg-white/5 hover:text-white" title="Log out"><x-icon name="logout" /></button>
            </form>
        </div>
    </div>
</aside>

<div class="lg:pl-64">
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur lg:hidden">
        <button type="button" class="rounded-lg p-1.5 text-gray-600 hover:bg-gray-100" @click="nav = true" aria-label="Open menu">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
        </button>
        <span class="font-semibold">{{ $currentWorkspace->name ?? \App\Services\Branding::appName() }}</span>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @include('partials.flash')
        {{ $slot }}
    </main>
</div>

@stack('scripts')
</body>
</html>

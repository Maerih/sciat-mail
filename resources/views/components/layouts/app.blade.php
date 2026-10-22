@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ \App\Services\Branding::appName() }}</title>
    @include('partials.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss = \App\Services\Branding::css(\App\Services\Branding::appColor()))<style>{!! $brandCss !!}</style>@endif
</head>
<body class="min-h-screen font-sans text-[14px] antialiased" x-data="appShell()" @keydown.window.prevent.ctrl.k="openSearch()" @keydown.window.prevent.meta.k="openSearch()">
@php
    $user = auth()->user();
    $nav = [
        [null, [['Home', 'dashboard', 'home', 'dashboard']]],
        ['Campaigns', [
            ['Email', 'campaigns.index', 'mail', 'campaigns.*'],
            ['Automation', 'automations.index', 'account_tree', 'automations.*'],
            ['Templates', 'templates.index', 'dashboard_customize', 'templates.*'],
            ['Assets', 'assets.index', 'perm_media', 'assets.*'],
        ]],
        ['Audience', [
            ['Overview', 'audience.overview', 'monitoring', 'audience.overview'],
            ['Lists', 'lists.index', 'format_list_bulleted', 'lists.*'],
            ['Subscribers', 'audience.subscribers', 'group', 'audience.subscribers'],
            ['Forms', 'forms.index', 'edit_note', 'forms.*'],
        ]],
        ['SMS', [
            ['SMS campaigns', 'sms.campaigns.index', 'sms', 'sms.campaigns.*'],
            ['SMS inbox', 'sms.inbox', 'forum', 'sms.inbox*'],
            ['SMS providers', 'sms.providers.index', 'settings_input_antenna', 'sms.providers.*'],
        ]],
        ['Sending', [
            ['Sending servers', 'sending.servers.index', 'dns', 'sending.servers.*'],
            ['Warmup strategies', 'sending.warmup.index', 'local_fire_department', 'sending.warmup.*'],
            ['Sending domains', 'sending.domains.index', 'language', 'sending.domains.*'],
            ['Tracking domains', 'sending.tracking-domains.index', 'link', 'sending.tracking-domains.*'],
            ['Email verification', 'sending.verification.index', 'verified', 'sending.verification.*'],
            ['Bounce handlers', 'sending.bounce-handlers.index', 'move_to_inbox', 'sending.bounce-handlers.*'],
            ['Blacklist', 'sending.blacklist.index', 'block', 'sending.blacklist.*'],
            ['Signatures', 'sending.signatures.index', 'draw', 'sending.signatures.*'],
        ]],
        ['Integration', array_values(array_filter([
            Route::has('websites.index') ? ['Websites', 'websites.index', 'public', 'websites.*'] : null,
            ['API & webhooks', 'api.settings', 'api', 'api.*'],
        ]))],
    ];
    if ($user->isAdmin()) {
        $nav[] = ['Administration', array_values(array_filter([
            ['Clients', 'workspaces.index', 'apartment', 'workspaces.index'],
            ['Team', 'team.index', 'groups', 'team.*'],
            Route::has('settings.index') ? ['Settings', 'settings.index', 'settings', 'settings.*'] : ['Settings', 'platform.edit', 'settings', 'platform.*'],
        ]))];
    }
    $searchItems = collect($nav)->flatMap(fn ($group) => collect($group[1])->map(fn ($i) => ['label' => $i[0], 'group' => $group[0] ?? 'General', 'icon' => $i[2], 'url' => route($i[1])]))
        ->push(['label' => 'New campaign', 'group' => 'Actions', 'icon' => 'add', 'url' => route('campaigns.create')])
        ->push(['label' => 'New list', 'group' => 'Actions', 'icon' => 'add', 'url' => route('lists.create')])
        ->push(['label' => 'New automation', 'group' => 'Actions', 'icon' => 'add', 'url' => route('automations.create')])
        ->push(['label' => 'Client settings', 'group' => 'Settings', 'icon' => 'tune', 'url' => route('workspaces.edit')])
        ->push(['label' => 'Your profile', 'group' => 'Settings', 'icon' => 'person', 'url' => route('profile.edit')])
        ->values();
@endphp

<div x-cloak x-show="nav" class="overlay fixed inset-0 z-30 lg:hidden" @click="nav = false"></div>

{{-- Sidebar --}}
<aside :class="nav ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col border-r border-[var(--line)] bg-[var(--sidebar)] transition-transform lg:translate-x-0"
       :style="collapsed && window.innerWidth >= 1024 ? 'transform: translateX(-100%)' : ''">
    <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-[var(--line)] px-4">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            @if ($platformLogo = \App\Services\Branding::appLogo())
                <img src="{{ $platformLogo }}" alt="" class="h-7 max-w-[9rem] object-contain">
            @else
                <span class="flex size-7 items-center justify-center rounded-md bg-brand-600 text-[var(--on-brand)]"><x-icon name="send" class="size-4" /></span>
            @endif
            <span class="truncate text-[15px] font-bold tracking-tight">{{ \App\Services\Branding::appName() }}</span>
        </a>
    </div>

    @isset($currentWorkspace)
        <div class="relative px-3 pt-3" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center gap-2.5 rounded-lg border border-[var(--line)] bg-[var(--surface-2)] px-2.5 py-2 text-left hover:border-brand-500/50">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-xs font-bold text-brand-600">{{ strtoupper(mb_substr($currentWorkspace->name, 0, 2)) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[10px] font-semibold tracking-wider text-[var(--muted)] uppercase">Client</span>
                    <span class="block truncate text-[13px] font-semibold">{{ $currentWorkspace->name }}</span>
                </span>
                <x-icon name="unfold_more" class="size-4 text-[var(--muted)]" />
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-3 right-3 z-50 mt-1 max-h-80 overflow-y-auto rounded-lg border border-[var(--line)] bg-[var(--surface)] p-1 shadow-2xl">
                @foreach ($allWorkspaces as $ws)
                    <form method="POST" action="{{ route('workspaces.switch', $ws->uid) }}">
                        @csrf
                        <button class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[13px] hover:bg-[var(--surface-2)]">
                            <span class="truncate">{{ $ws->name }}</span>
                            @if ($ws->id === $currentWorkspace->id)<x-icon name="check" class="size-4 text-brand-600" />@endif
                        </button>
                    </form>
                @endforeach
                <a href="{{ route('workspaces.create') }}" class="mt-1 flex items-center gap-2 border-t border-[var(--line)] px-2.5 py-2 text-[13px] font-medium text-brand-600 hover:bg-[var(--surface-2)]">
                    <x-icon name="add" class="size-4" /> New client
                </a>
            </div>
        </div>
    @endisset

    <nav class="flex-1 overflow-y-auto px-3 pb-4">
        @foreach ($nav as [$section, $items])
            @if ($section)<div class="nav-section">{{ $section }}</div>@else<div class="h-3"></div>@endif
            @foreach ($items as [$label, $route, $icon, $pattern])
                <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)])>
                    <x-icon :name="$icon" class="size-5" /> {{ $label }}
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="border-t border-[var(--line)] p-3" x-data="{ open: false }">
        <div x-show="open" x-cloak @click.outside="open = false" class="mb-2 rounded-lg border border-[var(--line)] bg-[var(--surface)] p-1 shadow-2xl">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] hover:bg-[var(--surface-2)]"><x-icon name="person" class="size-4.5" /> Your profile</a>
            <a href="{{ route('workspaces.edit') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] hover:bg-[var(--surface-2)]"><x-icon name="tune" class="size-4.5" /> Client settings</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-[13px] text-red-600 hover:bg-[var(--surface-2)]"><x-icon name="logout" class="size-4.5" /> Log out</button>
            </form>
        </div>
        <button type="button" @click="open = !open" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-[var(--surface-2)]">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[13px] font-semibold text-[var(--on-brand)]">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[13px] font-semibold">{{ $user->name }}</span>
                <span class="block truncate text-xs text-[var(--muted)]">{{ $user->email }}</span>
            </span>
            <x-icon name="more_vert" class="size-4.5 text-[var(--muted)]" />
        </button>
    </div>
</aside>

<div class="transition-[padding]" :class="collapsed ? 'lg:pl-0' : 'lg:pl-60'">
    {{-- Top bar --}}
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-[var(--line)] bg-[var(--surface)]/95 px-4 backdrop-blur">
        <button type="button" class="btn-ghost px-2" @click="window.innerWidth >= 1024 ? toggleCollapsed() : (nav = true)" aria-label="Toggle sidebar"><x-icon name="left_panel_open" class="size-5" /></button>
        <button type="button" @click="openSearch()" class="flex h-9 w-full max-w-md items-center gap-2 rounded-lg border border-[var(--line)] bg-[var(--surface-2)] px-3 text-left text-[13px] text-[var(--muted)] hover:border-brand-500/50">
            <x-icon name="search" class="size-4.5" />
            <span class="flex-1 truncate">Navigate your workspace instantly…</span>
            <kbd class="hidden rounded border border-[var(--line)] px-1.5 py-0.5 text-[10px] font-medium sm:inline">Ctrl K</kbd>
        </button>
        <div class="ml-auto flex items-center gap-1">
            <button type="button" class="btn-ghost px-2" @click="toggleTheme()" :title="theme === 'dark' ? 'Light mode' : 'Dark mode'">
                <x-icon name="dark_mode" class="size-5" x-show="theme !== 'dark'" /><x-icon name="light_mode" class="size-5" x-show="theme === 'dark'" x-cloak />
            </button>
            <a href="{{ route('api.docs') }}" class="btn-ghost px-2" title="API reference"><x-icon name="help" class="size-5" /></a>
            <a href="{{ route('campaigns.create') }}" class="btn-ghost px-2" title="New campaign"><x-icon name="rocket_launch" class="size-5" /></a>
        </div>
    </header>

    <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @if (auth()->user()->isAdmin() && Route::has('settings.maintenance') && \App\Services\DatabaseBackup::pendingMigrations())
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                <span class="flex items-center gap-2"><x-icon name="system_update" class="size-5" /> A new version was uploaded — apply its database changes to finish the update.</span>
                <a href="{{ route('settings.show', 'maintenance') }}" class="btn-primary btn-sm">Review update</a>
            </div>
        @endif
        @php $suspendedWorkspace = \App\Models\Workspace::current(); @endphp
        @if ($suspendedWorkspace?->isSuspended())
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <x-icon name="block" class="mt-0.5 size-5" />
                <div><p class="font-semibold">Sending is suspended for {{ $suspendedWorkspace->name }}</p><p>{{ $suspendedWorkspace->suspension_reason }}. Clean the affected lists, then an administrator can resume sending{!! auth()->user()->isAdmin() ? ' under <a href="'.route('settings.show', 'health').'" class="underline">Settings → Sending health</a>' : '' !!}.</p></div>
            </div>
        @endif
        @include('partials.flash')
        {{ $slot }}
    </main>
</div>

{{-- Command palette --}}
<div x-show="search" x-cloak class="overlay fixed inset-0 z-50 flex items-start justify-center p-4 pt-[12vh]" @keydown.escape.window="search = false">
    <div class="w-full max-w-xl overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--surface)] shadow-2xl" @click.outside="search = false">
        <div class="flex items-center gap-2 border-b border-[var(--line)] px-4">
            <x-icon name="search" class="size-5 text-[var(--muted)]" />
            <input x-ref="searchInput" x-model="query" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   class="h-12 w-full border-0 bg-transparent text-[15px] focus:ring-0" placeholder="Search pages and actions…">
            <kbd class="rounded border border-[var(--line)] px-1.5 py-0.5 text-[10px] text-[var(--muted)]">Esc</kbd>
        </div>
        <ul class="max-h-80 overflow-y-auto p-2">
            <template x-for="(item, i) in results()" :key="item.url + item.label">
                <li><a :href="item.url" @mouseenter="cursor = i" :class="cursor === i ? 'bg-[var(--surface-2)]' : ''" class="flex items-center gap-3 rounded-lg px-3 py-2.5">
                    <span class="material-symbols-rounded text-[var(--muted)]" style="font-size:20px" x-text="item.icon"></span>
                    <span class="flex-1 text-sm" x-text="item.label"></span>
                    <span class="text-xs text-[var(--muted)]" x-text="item.group"></span>
                </a></li>
            </template>
            <li x-show="results().length === 0" class="px-3 py-6 text-center text-sm text-[var(--muted)]">No matches</li>
        </ul>
    </div>
</div>

<script>
    function appShell() {
        return {
            nav: false, search: false, query: '', cursor: 0,
            collapsed: (() => { try { return localStorage.getItem('sidebar') === 'collapsed' } catch (e) { return false } })(),
            theme: document.documentElement.dataset.theme,
            items: @js($searchItems),
            toggleTheme() {
                this.theme = this.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.theme = this.theme;
                try { localStorage.setItem('theme', this.theme) } catch (e) {}
            },
            toggleCollapsed() {
                this.collapsed = !this.collapsed;
                try { localStorage.setItem('sidebar', this.collapsed ? 'collapsed' : 'open') } catch (e) {}
            },
            openSearch() { this.search = true; this.query = ''; this.cursor = 0; this.$nextTick(() => this.$refs.searchInput.focus()) },
            results() {
                const q = this.query.toLowerCase().trim();
                return q ? this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(q)).slice(0, 12) : this.items.slice(0, 12);
            },
            move(d) { const n = this.results().length; this.cursor = n ? (this.cursor + d + n) % n : 0 },
            go() { const item = this.results()[this.cursor]; if (item) window.location = item.url },
        };
    }
</script>
@stack('scripts')
</body>
</html>

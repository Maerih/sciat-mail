<x-layouts.app :title="$website->name">
    <x-page-header :title="$website->name" :subtitle="$website->url" :back="route('websites.index')">
        <x-slot:actions>
            @if ($website->isConnected())<span class="badge-green"><x-icon name="link" class="size-3.5" /> Connected</span>@else<span class="badge-gray"><x-icon name="link_off" class="size-3.5" /> Inactive</span>@endif
            <a href="{{ $website->url }}" target="_blank" rel="noopener" class="btn-secondary"><x-icon name="open_in_new" class="size-4.5" /> Visit site</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                <x-stat label="Page views" :value="number_format($stats['views'])" hint="Last 30 days" icon="visibility" />
                <x-stat label="Visitors" :value="number_format($stats['visitors'])" hint="Unique browsers" icon="group" />
                <x-stat label="Known contacts" :value="number_format($stats['known'])" hint="Arrived from your emails" icon="person_check" tone="green" />
                <x-stat label="Form signups" :value="number_format($stats['submissions'])" hint="Connected forms, all time" icon="how_to_reg" tone="amber" />
            </div>

            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Traffic</h2><p class="text-xs text-[var(--muted)]">Page views per day, last 30 days</p></div></div>
                <div class="card-body">
                    @if ($stats['views'] > 0)
                        <div class="h-64"><canvas id="traffic"></canvas></div>
                    @else
                        <div class="flex h-48 flex-col items-center justify-center text-center text-sm text-[var(--muted)]">
                            <x-icon name="monitoring" class="mb-2 size-8" />
                            No page views yet. They appear here once the tracking code is installed{{ $website->track_visits ? '' : ' and visit tracking is turned on' }}.
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Top pages</h2></div>
                    @forelse ($topPages as $page)
                        <div class="flex items-center justify-between gap-3 border-b border-[var(--line)] px-6 py-2.5 text-sm last:border-0">
                            <span class="truncate font-mono text-xs" title="{{ $page->path }}">{{ $page->path }}</span>
                            <span class="tabular-nums text-[var(--muted)]">{{ number_format($page->views) }}</span>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-[var(--muted)]">No data yet.</p>
                    @endforelse
                </div>
                <div class="card">
                    <div class="card-header"><div><h2 class="card-title">Known contacts on the site</h2><p class="text-xs text-[var(--muted)]">Subscribers who clicked through from an email</p></div></div>
                    @forelse ($contacts as $row)
                        @continue(! $row->subscriber)
                        <a href="{{ route('lists.subscribers.show', [$row->subscriber->list->uid, $row->subscriber->uid]) }}" class="flex items-center justify-between gap-3 border-b border-[var(--line)] px-6 py-2.5 text-sm last:border-0 hover:bg-[var(--surface-2)]">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $row->subscriber->email }}</span><span class="block text-xs text-[var(--muted)]">{{ $row->subscriber->list->name }}</span></span>
                            <span class="shrink-0 text-right text-xs text-[var(--muted)]">{{ $row->views }} {{ Str::plural('view', $row->views) }}<br>{{ \Illuminate\Support\Carbon::parse($row->last_visit)->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-[var(--muted)]">When subscribers click a link to {{ $website->host }} in your emails, their visits show up here.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="min-w-0 space-y-6">
            <div class="card" x-data="copy(@js($website->snippet()))">
                <div class="card-header"><div class="flex items-center gap-2"><x-icon name="cable" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Connection</h2></div>
                    @if ($website->isConnected())<span class="badge-green">Active</span>@else<span class="badge-gray">Inactive</span>@endif
                </div>
                <div class="card-body space-y-3">
                    <label class="label">Tracking code</label>
                    <div class="relative"><pre class="code pr-10 whitespace-pre-wrap">{{ $website->snippet() }}</pre>
                        <button type="button" class="absolute top-2 right-2 rounded p-1 text-[var(--editor-text)] opacity-70 hover:opacity-100" @click="copy()" title="Copy"><x-icon name="content_copy" class="size-4" x-show="!copied" /><x-icon name="check" class="size-4" x-show="copied" x-cloak /></button>
                    </div>
                    <p class="help">Paste this snippet before the closing <code>&lt;/body&gt;</code> tag of your website — once, on every page.</p>
                    @if ($website->check_error)
                        <p class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $website->check_error }}</p>
                    @elseif ($website->isConnected())
                        <p class="text-xs text-[var(--muted)]">Connected {{ $website->connected_at?->diffForHumans() }}@if ($website->last_seen_at) · last page view {{ $website->last_seen_at->diffForHumans() }}@endif</p>
                    @endif
                    <form method="POST" action="{{ route('websites.verify', $website) }}">@csrf
                        <button class="btn-primary btn-sm"><x-icon name="link" class="size-4" /> {{ $website->isConnected() ? 'Check again' : 'Connect' }}</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="flex items-center gap-2"><x-icon name="web" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Connected forms</h2></div><span class="text-xs text-[var(--muted)]">{{ $forms->count() }}</span></div>
                @foreach ($forms as $form)
                    <div class="border-b border-[var(--line)] px-6 py-3 text-sm" x-data="copy(@js('<div data-sm-form="'.$form->uid.'"></div>'))">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('forms.edit', $form) }}" class="block truncate font-medium hover:text-brand-600">{{ $form->name }}</a>
                                <span class="text-xs text-[var(--muted)]">{{ $form->list->name }} · {{ $form->isPopup() ? 'Popup — shows automatically' : 'Inline' }} @if ($form->status !== 'published')· <span class="text-amber-600">draft</span>@endif</span>
                            </div>
                            <form method="POST" action="{{ route('websites.forms.disconnect', [$website, $form->uid]) }}">@csrf @method('DELETE')
                                <button class="btn-ghost btn-sm" title="Disconnect"><x-icon name="link_off" class="size-4" /></button>
                            </form>
                        </div>
                        @unless ($form->isPopup())
                            <button type="button" class="mt-1.5 text-xs text-brand-600 hover:underline" @click="copy()" x-text="copied ? 'Copied!' : 'Copy placeholder: <div data-sm-form=&quot;{{ $form->uid }}&quot;>'"></button>
                        @endunless
                    </div>
                @endforeach
                <div class="card-body">
                    @if ($availableForms->isEmpty())
                        <p class="text-sm text-[var(--muted)]">No other forms to connect. <a href="{{ route('forms.create') }}" class="text-brand-600 hover:underline">Create a form</a>.</p>
                    @else
                        <form method="POST" action="{{ route('websites.forms.connect', $website) }}" class="flex gap-2">@csrf
                            <select name="form" class="input" required>
                                <option value="">Connect a form…</option>
                                @foreach ($availableForms as $form)
                                    <option value="{{ $form->uid }}">{{ $form->name }} ({{ $form->list->name }}){{ $form->website_id ? ' — moves from another site' : '' }}</option>
                                @endforeach
                            </select>
                            <button class="btn-secondary"><x-icon name="add_link" class="size-4.5" /></button>
                        </form>
                        <p class="help">Popup forms load automatically. Inline forms render wherever you place their placeholder.</p>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('websites.update', $website) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><div class="flex items-center gap-2"><x-icon name="tune" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Settings</h2></div></div>
                <div class="card-body space-y-4">
                    <x-field name="url" type="url" label="Website URL" :value="$website->url" required help="Changing the domain disconnects the site until it is checked again." />
                    <x-field name="name" label="Name" :value="$website->name" />
                    <x-toggle name="track_visits" label="Track page views" help="Counts visits and links them to contacts who arrive from your emails." :checked="$website->track_visits" />
                    <div class="flex items-center justify-between">
                        <button class="btn-primary btn-sm"><x-icon name="save" class="size-4" /> Save changes</button>
                    </div>
                </div>
            </form>
            <div class="flex justify-end"><x-confirm-delete :action="route('websites.destroy', $website)" label="Remove website" /></div>
        </div>
    </div>

    @if ($stats['views'] > 0)
        @push('scripts')
            <script type="module">
                const data = @js($chart);
                new Chart(document.getElementById('traffic'), {
                    type: 'bar',
                    data: { labels: Object.keys(data).map(d => d.slice(5)), datasets: [{ label: 'Page views', data: Object.values(data), backgroundColor: themeColor('--color-brand-500'), borderRadius: 4, maxBarThickness: 16 }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } } } },
                });
            </script>
        @endpush
    @endif
</x-layouts.app>

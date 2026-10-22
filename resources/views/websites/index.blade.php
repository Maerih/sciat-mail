<x-layouts.app title="Websites">
    <x-page-header title="Websites" subtitle="Connect your websites to track visitors and embed signup forms.">
        <x-slot:actions><a href="{{ route('websites.create') }}" class="btn-primary"><x-icon name="add" /> Add website</a></x-slot:actions>
    </x-page-header>
    <x-hero icon="language" title="Connect your websites" text="Link your websites to track visitor behaviour, embed signup forms, and automatically sync new contacts into your lists."
        :stats="[['language', $websites->total(), 'websites'], ['link', $websites->where('status', 'connected')->count(), 'connected']]"
        :actions="[['add_link', 'Add your website', 'Enter your domain URL and connect it to your account.', route('websites.create')],
                   ['code', 'Install tracking code', 'Copy a small JavaScript snippet into your site\'s HTML.', route('websites.create')],
                   ['rocket_launch', 'Go live', 'Visitors and form submissions start flowing in automatically.', route('forms.index')]]" />
    <div class="card">
        @if ($websites->isEmpty())
            <x-empty title="No websites yet" icon="language">Add a website, paste one script tag into its HTML and your forms go live there.
                <x-slot:action><a href="{{ route('websites.create') }}" class="btn-primary"><x-icon name="add" /> Add website</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Website</th><th>Status</th><th class="text-right">Forms</th><th class="text-right">Page views (30d)</th><th>Last activity</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($websites as $website)
                            <tr>
                                <td>
                                    <a href="{{ route('websites.show', $website) }}" class="flex items-center gap-3">
                                        <span class="icon-tile size-9 bg-brand-50 text-brand-600"><x-icon name="language" class="size-5" /></span>
                                        <span><span class="block font-medium hover:text-brand-600">{{ $website->name }}</span><span class="block text-xs text-[var(--muted)]">{{ $website->url }}</span></span>
                                    </a>
                                </td>
                                <td>@if ($website->isConnected())<span class="badge-green"><x-icon name="link" class="size-3.5" /> Connected</span>@else<span class="badge-gray"><x-icon name="link_off" class="size-3.5" /> Inactive</span>@endif</td>
                                <td class="text-right tabular-nums">{{ $website->forms_count }}</td>
                                <td class="text-right tabular-nums">{{ number_format($website->visits_count) }}</td>
                                <td class="text-[var(--muted)]">{{ $website->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('websites.show', $website) }}" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" /> Manage</a>
                                    <x-confirm-delete :action="route('websites.destroy', $website)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $websites->links() }}
        @endif
    </div>
</x-layouts.app>

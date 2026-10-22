<x-layouts.app title="Subscribers">
    <x-page-header title="Subscribers" subtitle="Every contact across all of this client's lists.">
        <x-slot:actions><a href="{{ route('audience.overview') }}" class="btn-secondary"><x-icon name="monitoring" class="size-4.5" /> Overview</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        <form class="flex flex-wrap gap-2 border-b border-[var(--line)] p-4">
            <div class="relative min-w-60 flex-1"><x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4.5 text-[var(--muted)]" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by email…" class="input pl-10"></div>
            <select name="list" class="input w-auto" onchange="this.form.submit()">
                <option value="">All lists</option>
                @foreach ($lists as $list)<option value="{{ $list->uid }}" @selected(request('list') === $list->uid)>{{ $list->name }}</option>@endforeach
            </select>
            <select name="status" class="input w-auto" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (\App\Models\Subscriber::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach
            </select>
            <button class="btn-secondary">Filter</button>
        </form>
        @if ($subscribers->isEmpty())
            <x-empty title="No subscribers found" icon="group">Try another search, or import contacts into a list.</x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Email</th><th>List</th><th>Status</th><th>Tags</th><th>Source</th><th>Added</th></tr></thead>
                    <tbody>
                        @foreach ($subscribers as $subscriber)
                            <tr>
                                <td><a href="{{ route('lists.subscribers.show', [$subscriber->list, $subscriber]) }}" class="flex items-center gap-3 font-medium hover:text-brand-600">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 uppercase">{{ substr($subscriber->email, 0, 2) }}</span>{{ $subscriber->email }}</a></td>
                                <td><a href="{{ route('lists.show', $subscriber->list) }}" class="text-[var(--muted)] hover:text-brand-600">{{ $subscriber->list->name }}</a></td>
                                <td><x-status-badge :status="$subscriber->status" /></td>
                                <td>@foreach ($subscriber->tags ?? [] as $tag)<span class="badge-purple mr-1">{{ $tag }}</span>@endforeach</td>
                                <td class="text-[var(--muted)]">{{ ucfirst($subscriber->source ?? '—') }}</td>
                                <td class="whitespace-nowrap text-[var(--muted)]">{{ $subscriber->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subscribers->links() }}
        @endif
    </div>
</x-layouts.app>

<x-layouts.app title="Lists">
    <x-page-header title="Lists" subtitle="Collections of contacts who opted in to this client's emails.">
        <x-slot:actions><a href="{{ route('lists.create') }}" class="btn-primary"><x-icon name="plus" /> New list</a></x-slot:actions>
    </x-page-header>

    <div class="card">
        <form class="flex flex-wrap gap-2 border-b border-gray-100 p-3">
            <input name="q" value="{{ request('q') }}" placeholder="Search lists…" class="input max-w-xs">
            <select name="sort" class="input w-auto" onchange="this.form.submit()">
                <option value="">Newest first</option>
                <option value="name" @selected(request('sort') === 'name')>Name</option>
            </select>
        </form>
        @if ($lists->isEmpty())
            <x-empty title="No lists yet" icon="users">Create a list, then import contacts or embed a signup form.
                <x-slot:action><a href="{{ route('lists.create') }}" class="btn-primary"><x-icon name="plus" /> New list</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>List</th><th>Subscribed</th><th>Total</th><th>Segments</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($lists as $list)
                            <tr>
                                <td>
                                    <a href="{{ route('lists.show', $list) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $list->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $list->from_name }} &lt;{{ $list->from_email }}&gt;</div>
                                </td>
                                <td class="tabular-nums">{{ number_format($list->subscribed_count) }}</td>
                                <td class="tabular-nums">{{ number_format($list->subscribers_count) }}</td>
                                <td>{{ $list->segments_count }}</td>
                                <td class="text-gray-500">{{ $list->created_at->format('M j, Y') }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('lists.subscribers.index', $list) }}" class="btn-ghost btn-sm">Subscribers</a>
                                    <a href="{{ route('lists.import.create', $list) }}" class="btn-ghost btn-sm">Import</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $lists->links() }}
        @endif
    </div>
</x-layouts.app>

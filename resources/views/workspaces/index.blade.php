<x-layouts.app title="All clients">
    <x-page-header title="All clients">
        <x-slot:actions><a href="{{ route('workspaces.create') }}" class="btn-primary"><x-icon name="plus" /> New client</a></x-slot:actions>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Client</th><th>Lists</th><th>Campaigns</th><th>Timezone</th><th></th></tr></thead>
            <tbody>
                @foreach ($workspaces as $ws)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $ws->name }}</td>
                        <td>{{ $ws->lists_count }}</td>
                        <td>{{ $ws->campaigns_count }}</td>
                        <td>{{ $ws->timezone }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('workspaces.switch', $ws->uid) }}">@csrf<button class="btn-secondary btn-sm">Open</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>

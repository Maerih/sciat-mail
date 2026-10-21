<x-layouts.app title="Client settings">
    <x-page-header title="Client settings" :subtitle="$workspace->name" />
    <form method="POST" action="{{ route('workspaces.update') }}" class="card card-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        @include('workspaces._fields')
        <div class="flex justify-end"><button class="btn-primary">Save settings</button></div>
    </form>

    @if (auth()->user()->isAdmin())
        <form method="POST" action="{{ route('workspaces.destroy') }}" class="card card-body mt-6 max-w-2xl space-y-3 border-red-200">
            @csrf @method('DELETE')
            <h2 class="text-sm font-semibold text-red-700">Delete this client</h2>
            <p class="text-sm text-gray-600">Permanently deletes every list, subscriber, campaign, report and domain of this client.</p>
            <x-field name="confirm" :label="'Type “'.$workspace->name.'” to confirm'" />
            <button class="btn-danger">Delete client</button>
        </form>
    @endif
</x-layouts.app>

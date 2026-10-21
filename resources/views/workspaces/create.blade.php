<x-layouts.app title="New client">
    <x-page-header title="Add a client" subtitle="Each client gets its own lists, campaigns, domains and reports." />
    <form method="POST" action="{{ route('workspaces.store') }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @include('workspaces._fields', ['workspace' => new \App\Models\Workspace(['timezone' => 'Africa/Nairobi'])])
        <div class="flex justify-end"><button class="btn-primary">Create client</button></div>
    </form>
</x-layouts.app>

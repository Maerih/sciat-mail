<x-layouts.app title="Blacklist">
    <x-page-header title="Blacklist" subtitle="Suppressed addresses never receive campaigns. Hard bounces and spam complaints are added automatically." />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <form class="flex flex-wrap gap-2 border-b border-gray-100 p-3">
                <input name="q" value="{{ request('q') }}" placeholder="Search email…" class="input max-w-xs">
                <select name="reason" class="input w-auto" onchange="this.form.submit()">
                    <option value="">All reasons</option>
                    @foreach (\App\Models\Blacklist::REASONS as $key => $label)<option value="{{ $key }}" @selected(request('reason') === $key)>{{ $label }}</option>@endforeach
                </select>
            </form>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Email</th><th>Reason</th><th>Scope</th><th>Added</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $entry->email }}</td>
                                <td><span class="{{ in_array($entry->reason, ['hard_bounce', 'complaint']) ? 'badge-red' : 'badge-gray' }}">{{ \App\Models\Blacklist::REASONS[$entry->reason] ?? $entry->reason }}</span></td>
                                <td>{{ $entry->workspace_id ? 'This client' : 'All clients' }}</td>
                                <td class="text-gray-500">{{ $entry->created_at->diffForHumans() }}</td>
                                <td class="text-right">
                                    @if ($entry->workspace_id || auth()->user()->isAdmin())
                                        <x-confirm-delete :action="route('sending.blacklist.destroy', $entry)" label="Remove" :message="'Remove '.$entry->email.' from the blacklist? Their subscriptions become unsubscribed, not subscribed.'" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-gray-500">The blacklist is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $entries->links() }}
        </div>
        <form method="POST" action="{{ route('sending.blacklist.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="card-title">Add addresses</h2>
            <x-field name="emails" type="textarea" rows="6" label="Emails" placeholder="one@example.com&#10;two@example.com" help="One per line or comma separated." required />
            @if (auth()->user()->isAdmin())
                <x-toggle name="global" label="Block for all clients" />
            @endif
            <button class="btn-primary w-full">Blacklist</button>
        </form>
    </div>
</x-layouts.app>

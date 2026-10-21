<x-layouts.app :title="'Contacts · '.$automation->name">
    <x-page-header :title="$automation->name" subtitle="Contacts who entered this automation and where they are now." :back="route('automations.show', $automation)" />
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'waiting' => 'Waiting', 'completed' => 'Completed', 'exited' => 'Exited', 'failed' => 'Failed'] as $value => $label)
            <a href="{{ route('automations.contacts', [$automation, 'status' => $value ?: null]) }}" @class(['badge px-3 py-1 text-sm', 'bg-gray-900 text-white' => request('status', '') === $value, 'bg-white text-gray-700 ring-1 ring-gray-200' => request('status', '') !== $value])>{{ $label }}</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Contact</th><th>Status</th><th>Current step</th><th>Next action</th><th>Last activity</th></tr></thead>
            <tbody>
                @forelse ($runs as $run)
                    @php $step = $flow->get($run->step_id); $last = collect($run->history ?? [])->last(); @endphp
                    <tr>
                        <td>@if ($run->subscriber)<a class="font-medium text-gray-900 hover:text-brand-600" href="{{ route('lists.subscribers.show', [$automation->list, $run->subscriber]) }}">{{ $run->subscriber->email }}</a>@endif</td>
                        <td><x-status-badge :status="$run->status" /></td>
                        <td>{{ $step ? \App\Services\Automation\Flow::TYPES[$step['type']] : '—' }}</td>
                        <td class="text-gray-500">{{ $run->wake_at ? 'Continues '.$run->wake_at->diffForHumans() : '—' }}</td>
                        <td class="text-gray-500">{{ $last['note'] ?? '' }} <span class="text-xs text-gray-400">{{ $run->updated_at->diffForHumans() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-gray-500">Nobody has entered this automation yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $runs->links() }}
    </div>
</x-layouts.app>

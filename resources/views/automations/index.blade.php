<x-layouts.app title="Automations">
    <x-page-header title="Automations" subtitle="Journeys that run on their own: welcome series, drips, birthdays, re-engagement and more.">
        <x-slot:actions><a href="{{ route('automations.create') }}" class="btn-primary"><x-icon name="add" /> Create automation</a></x-slot:actions>
    </x-page-header>
    <x-hero icon="account_tree" title="Put your emails on autopilot" text="Create automated workflows that send the right message at the right time — without lifting a finger."
        :stats="[['bolt', $automations->total(), 'automations'], ['play_circle', $automations->where('status', 'active')->count(), 'active']]" />
    <div class="card">
        @if ($automations->isEmpty())
            <x-empty title="No automations yet" icon="bolt">Welcome new subscribers, send birthday offers or nurture leads automatically.
                <x-slot:action><a href="{{ route('automations.create') }}" class="btn-primary"><x-icon name="plus" /> New automation</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Automation</th><th>List</th><th>Status</th><th>In progress</th><th>Completed</th><th>Total entered</th></tr></thead>
                    <tbody>
                        @foreach ($automations as $automation)
                            <tr>
                                <td>
                                    <a href="{{ route('automations.show', $automation) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $automation->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $automation->triggerSummary() }}</div>
                                </td>
                                <td>{{ $automation->list->name }}</td>
                                <td><x-status-badge :status="$automation->status" /></td>
                                <td class="tabular-nums">{{ number_format($automation->active_runs_count) }}</td>
                                <td class="tabular-nums">{{ number_format($automation->completed_runs_count) }}</td>
                                <td class="tabular-nums">{{ number_format($automation->runs_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $automations->links() }}
        @endif
    </div>
</x-layouts.app>

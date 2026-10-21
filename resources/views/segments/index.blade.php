<x-layouts.app :title="$list->name.' segments'">
    <x-page-header :title="$list->name" :back="route('lists.index')">
        <x-slot:actions><a href="{{ route('lists.segments.create', $list) }}" class="btn-primary"><x-icon name="plus" /> New segment</a></x-slot:actions>
    </x-page-header>
    @include('lists._tabs')

    <div class="card">
        @if ($segments->isEmpty())
            <x-empty title="No segments yet" icon="filter">Segments group subscribers by fields, tags or engagement so you can target campaigns.
                <x-slot:action><a href="{{ route('lists.segments.create', $list) }}" class="btn-primary"><x-icon name="plus" /> New segment</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Segment</th><th>Conditions</th><th>Subscribers</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($segments as $segment)
                            <tr>
                                <td><a href="{{ route('lists.segments.edit', [$list, $segment]) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $segment->name }}</a></td>
                                <td class="text-gray-500">{{ count($segment->conditions ?? []) }} · match {{ $segment->matching }}</td>
                                <td>
                                    <a href="{{ route('lists.subscribers.index', [$list, 'segment' => $segment->uid]) }}" class="tabular-nums hover:underline">{{ number_format($segment->size) }}</a>
                                    <span class="text-xs text-gray-400">({{ $total ? round($segment->size / $total * 100) : 0 }}%)</span>
                                </td>
                                <td class="text-right"><x-confirm-delete :action="route('lists.segments.destroy', [$list, $segment])" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

<x-layouts.app title="Map columns">
    <x-page-header title="Map your columns" :subtitle="$import->original_name.' · about '.number_format($import->total).' rows'" :back="route('lists.import.create', $list)" />

    <form method="POST" action="{{ route('lists.import.start', [$list, $import]) }}" class="card overflow-x-auto">
        @csrf
        <table class="table">
            <thead><tr><th>Column in file</th><th>Sample values</th><th class="w-64">Import as</th></tr></thead>
            <tbody>
                @foreach ($headers as $index => $header)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $header !== '' ? $header : 'Column '.($index + 1) }}</td>
                        <td class="max-w-sm truncate text-gray-500">{{ collect($preview)->pluck($index)->filter()->take(3)->implode(' · ') }}</td>
                        <td>
                            <select name="mapping[{{ $index }}]" class="input">
                                <option value="">Don't import</option>
                                <option value="EMAIL" @selected($guesses[$index] === 'EMAIL')>Email</option>
                                @foreach ($list->fields as $field)<option value="{{ $field->tag }}" @selected($guesses[$index] === $field->tag)>{{ $field->label }}</option>@endforeach
                                <option value="TAGS" @selected($guesses[$index] === 'TAGS')>Tags (comma separated)</option>
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex items-center justify-between gap-4 border-t border-gray-100 px-5 py-4">
            <p class="text-sm text-gray-500">Need another field? <a href="{{ route('lists.fields', $list) }}" class="text-brand-600 hover:underline">Add it to the list</a> first.</p>
            <button class="btn-primary">Start import</button>
        </div>
    </form>
</x-layouts.app>

<x-layouts.app :title="'Import into '.$list->name">
    <x-page-header :title="$list->name" :back="route('lists.index')" />
    @include('lists._tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('lists.import.upload', $list) }}" enctype="multipart/form-data" class="card card-body space-y-4 lg:col-span-2"
              x-data="{ name: '' }">
            @csrf
            <h2 class="card-title">Upload a CSV file</h2>
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-12 text-center hover:border-brand-500 hover:bg-brand-50/30">
                <x-icon name="upload" class="size-8 text-gray-400" />
                <span class="mt-2 text-sm font-medium text-gray-900" x-text="name || 'Choose a .csv or .txt file'"></span>
                <span class="text-xs text-gray-500">Comma, semicolon, tab or pipe separated · up to 100 MB</span>
                <input type="file" name="file" accept=".csv,.txt" class="sr-only" required @change="name = $event.target.files[0]?.name">
            </label>
            @error('file')<p class="error">{{ $message }}</p>@enderror
            <div class="flex justify-end"><button class="btn-primary">Continue to column mapping</button></div>
        </form>

        <div class="card card-body text-sm text-gray-600">
            <h2 class="card-title mb-3">Before you start</h2>
            <ul class="list-inside list-disc space-y-1.5">
                <li>The first row must contain column headers.</li>
                <li>An email column is required.</li>
                <li>Existing subscribers are updated, not duplicated.</li>
                <li>Blacklisted addresses are imported as blacklisted.</li>
                <li>Only import people who agreed to hear from this client.</li>
            </ul>
        </div>
    </div>

    @if ($recent->isNotEmpty())
        <div class="card mt-6 overflow-x-auto">
            <div class="card-header"><h2 class="card-title">Recent imports</h2></div>
            <table class="table">
                <thead><tr><th>File</th><th>Status</th><th>New</th><th>Updated</th><th>Failed</th><th>Date</th></tr></thead>
                <tbody>
                    @foreach ($recent as $import)
                        <tr>
                            <td><a href="{{ route('lists.import.show', [$list, $import]) }}" class="font-medium hover:text-brand-600">{{ $import->original_name }}</a></td>
                            <td><x-status-badge :status="$import->status" /></td>
                            <td>{{ number_format($import->created_count) }}</td>
                            <td>{{ number_format($import->updated_count) }}</td>
                            <td>{{ number_format($import->failed_count) }}</td>
                            <td class="text-gray-500">{{ $import->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>

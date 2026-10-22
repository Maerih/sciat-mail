<x-layouts.portal title="Audience">
    <x-page-header title="Audience" subtitle="How your mailing lists are growing." />
    <div class="grid gap-5 md:grid-cols-2">
        @forelse ($lists as $row)
            <div class="card card-body">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ $row['list']->name }}</h2>
                    <span class="badge-green">+{{ number_format($row['new30']) }} in 30 days</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    @foreach ($row['counts'] as $status => $count)
                        <div><dt class="text-xs text-gray-500 uppercase">{{ $status }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($count) }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @empty
            <div class="card card-body text-sm text-gray-500 md:col-span-2">No lists yet.</div>
        @endforelse
    </div>
</x-layouts.portal>

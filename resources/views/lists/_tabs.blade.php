<div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
    @foreach ([
        ['Overview', route('lists.show', $list), 'lists.show'],
        ['Subscribers', route('lists.subscribers.index', $list), 'lists.subscribers.*'],
        ['Segments', route('lists.segments.index', $list), 'lists.segments.*'],
        ['Import', route('lists.import.create', $list), 'lists.import.*'],
        ['Fields', route('lists.fields', $list), 'lists.fields'],
        ['Settings', route('lists.edit', $list), 'lists.edit'],
    ] as [$label, $url, $pattern])
        <a href="{{ $url }}" @class(['tab', 'active' => request()->routeIs($pattern)])>{{ $label }}</a>
    @endforeach
</div>

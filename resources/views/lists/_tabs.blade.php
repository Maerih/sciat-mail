<x-pill-tabs :items="[
    ['Overview', route('lists.show', $list), request()->routeIs('lists.show'), 'auto_graph'],
    ['Subscribers', route('lists.subscribers.index', $list), request()->routeIs('lists.subscribers.*'), 'group'],
    ['Segments', route('lists.segments.index', $list), request()->routeIs('lists.segments.*'), 'splitscreen'],
    ['Import', route('lists.import.create', $list), request()->routeIs('lists.import.*'), 'upload_file'],
    ['Fields', route('lists.fields', $list), request()->routeIs('lists.fields'), 'fact_check'],
    ['Settings', route('lists.edit', $list), request()->routeIs('lists.edit'), 'settings'],
]" />

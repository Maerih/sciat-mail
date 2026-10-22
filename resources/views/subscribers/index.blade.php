<x-layouts.app :title="$list->name.' subscribers'">
    <x-page-header :title="$list->name" :back="route('lists.index')">
        <x-slot:actions>
            <a href="{{ route('lists.subscribers.export', [$list] + request()->only('status', 'q', 'tag', 'segment', 'verification')) }}" class="btn-secondary"><x-icon name="download" /> Export CSV</a>
            <a href="{{ route('lists.subscribers.create', $list) }}" class="btn-primary"><x-icon name="plus" /> Add subscriber</a>
        </x-slot:actions>
    </x-page-header>
    @include('lists._tabs')

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('lists.subscribers.index', $list) }}" @class(['badge px-3 py-1 text-sm', 'bg-gray-900 text-white' => ! request('status'), 'bg-white text-gray-700 ring-1 ring-gray-200' => request('status')])>All {{ number_format(array_sum($counts)) }}</a>
        @foreach ($counts as $status => $count)
            <a href="{{ route('lists.subscribers.index', [$list, 'status' => $status]) }}" @class(['badge px-3 py-1 text-sm', 'bg-gray-900 text-white' => request('status') === $status, 'bg-white text-gray-700 ring-1 ring-gray-200' => request('status') !== $status])>{{ ucfirst($status) }} {{ number_format($count) }}</a>
        @endforeach
    </div>

    <div class="card" x-data="{ selected: [], scope: 'selected', action: '', all: false,
            toggleAll(e) { this.selected = e.target.checked ? [...document.querySelectorAll('[data-row]')].map(c => c.value) : []; this.all = false } }">
        <form class="flex flex-wrap gap-2 border-b border-gray-100 p-3">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search email or name…" class="input max-w-xs">
            <input name="tag" value="{{ request('tag') }}" placeholder="Tag" class="input w-32">
            <select name="segment" class="input w-auto">
                <option value="">All subscribers</option>
                @foreach ($segments as $segment)<option value="{{ $segment->uid }}" @selected(request('segment') === $segment->uid)>{{ $segment->name }}</option>@endforeach
            </select>
            <select name="verification" class="input w-auto">
                <option value="">Any verification</option>
                @foreach (\App\Models\VerificationServer::STATUSES as $v)<option value="{{ $v }}" @selected(request('verification') === $v)>{{ ucfirst($v) }}</option>@endforeach
            </select>
            <select name="sort" class="input w-auto">
                <option value="">Newest</option>
                <option value="email" @selected(request('sort') === 'email')>Email</option>
                <option value="updated" @selected(request('sort') === 'updated')>Recently updated</option>
            </select>
            <button class="btn-secondary"><x-icon name="filter" /> Filter</button>
        </form>

        <form method="POST" action="{{ route('lists.subscribers.bulk', [$list] + request()->only('status', 'q', 'tag', 'segment', 'verification')) }}"
              x-show="selected.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-gray-100 bg-brand-50/60 px-4 py-2.5 text-sm"
              @submit="if (action === 'delete' && !confirm('Delete the selected subscribers?')) $event.preventDefault()">
            @csrf
            <template x-for="id in selected"><input type="hidden" name="ids[]" :value="id"></template>
            <input type="hidden" name="scope" :value="all ? 'all' : 'selected'">
            <span class="font-medium" x-text="all ? 'All {{ number_format($subscribers->total()) }} matching subscribers' : selected.length + ' selected'"></span>
            @if ($subscribers->total() > $subscribers->count())
                <button type="button" class="text-brand-700 underline" x-show="!all" @click="all = true">Select all {{ number_format($subscribers->total()) }}</button>
            @endif
            <select name="action" x-model="action" class="input w-auto py-1.5" required>
                <option value="">Choose action…</option>
                @foreach (\App\Http\Controllers\SubscriberController::BULK_ACTIONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <input name="tags" x-show="action === 'tag' || action === 'untag'" placeholder="tag1, tag2" class="input w-44 py-1.5">
            <select name="target_list" x-show="action === 'copy' || action === 'move'" class="input w-auto py-1.5">
                @foreach ($otherLists as $other)<option value="{{ $other->uid }}">{{ $other->name }}</option>@endforeach
            </select>
            <button class="btn-primary btn-sm">Apply</button>
        </form>

        @if ($subscribers->isEmpty())
            <x-empty title="No subscribers found" icon="users">Import a CSV, add contacts manually or embed a signup form.
                <x-slot:action><a href="{{ route('lists.import.create', $list) }}" class="btn-primary"><x-icon name="upload" /> Import contacts</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr>
                        <th class="w-8"><input type="checkbox" class="rounded border-gray-300 text-brand-600" @change="toggleAll"></th>
                        <th>Email</th>
                        @foreach ($fields->take(3) as $field)<th>{{ $field->label }}</th>@endforeach
                        <th>Status</th><th>Tags</th><th>Added</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($subscribers as $subscriber)
                            <tr>
                                <td><input type="checkbox" data-row value="{{ $subscriber->uid }}" x-model="selected" class="rounded border-gray-300 text-brand-600"></td>
                                <td><a href="{{ route('lists.subscribers.show', [$list, $subscriber]) }}" class="flex items-center gap-3 font-medium hover:text-brand-600"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 uppercase">{{ substr($subscriber->email, 0, 1) }}</span>{{ $subscriber->email }}</a></td>
                                @foreach ($fields->take(3) as $field)<td>{{ $subscriber->field($field->tag) }}</td>@endforeach
                                <td><x-status-badge :status="$subscriber->status" />
                                    @if ($subscriber->verification_status !== 'unverified')
                                        <span title="{{ $subscriber->verification_reason }}" @class(['badge ml-1', 'bg-emerald-50 text-emerald-700' => $subscriber->verification_status === 'deliverable', 'bg-amber-50 text-amber-700' => in_array($subscriber->verification_status, ['risky', 'unknown']), 'bg-red-50 text-red-700' => $subscriber->verification_status === 'undeliverable'])>{{ $subscriber->verification_status }}</span>
                                    @endif
                                </td>
                                <td>@foreach ($subscriber->tags ?? [] as $tag)<span class="badge-purple mr-1">{{ $tag }}</span>@endforeach</td>
                                <td class="whitespace-nowrap text-gray-500">{{ $subscriber->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subscribers->links() }}
        @endif
    </div>
</x-layouts.app>

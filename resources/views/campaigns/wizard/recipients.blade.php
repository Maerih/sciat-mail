<x-layouts.app :title="'Recipients · '.$campaign->name">
    @include('campaigns.wizard._layout')

    @php
        $rows = old('audiences', $audiences->map(fn ($a) => ['list' => $a->list->uid, 'segment' => $a->segment?->uid, 'exclude' => $a->exclude])->values()->all())
            ?: [['list' => $lists->first()?->uid, 'segment' => null, 'exclude' => false]];
        $listData = $lists->mapWithKeys(fn ($l) => [$l->uid => ['name' => $l->name, 'count' => $l->subscribed_count, 'segments' => $l->segments->map(fn ($s) => ['uid' => $s->uid, 'name' => $s->name])->values()]]);
    @endphp

    @if ($lists->isEmpty())
        <div class="card"><x-empty title="No lists yet" icon="users">Create a list and add subscribers before building a campaign.
            <x-slot:action><a href="{{ route('lists.create') }}" class="btn-primary">Create list</a></x-slot:action></x-empty></div>
    @else
        <form method="POST" action="{{ route('campaigns.recipients.save', $campaign) }}" class="max-w-3xl space-y-6"
              x-data="{ rows: @js($rows), lists: @js($listData) }">
            @csrf
            @foreach ([false => ['Send to', 'Pick one or more lists. Narrow each list with a segment if you like.'], true => ['Exclude', 'Optional: anyone in these lists/segments will not receive this campaign.']] as $exclude => [$title, $help])
                <div class="card card-body space-y-3">
                    <div><h2 class="card-title">{{ $title }}</h2><p class="text-sm text-gray-500">{{ $help }}</p></div>
                    <template x-for="(row, i) in rows" :key="i">
                        <div x-show="!!row.exclude === {{ $exclude ? 'true' : 'false' }}" class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 p-3">
                            <input type="hidden" :name="`audiences[${i}][exclude]`" :value="row.exclude ? 1 : 0">
                            <select class="input w-64" :name="`audiences[${i}][list]`" x-model="row.list" @change="row.segment = ''">
                                <template x-for="(l, uid) in lists"><option :value="uid" x-text="`${l.name} (${l.count.toLocaleString()})`" :selected="uid === row.list"></option></template>
                            </select>
                            <select class="input w-56" :name="`audiences[${i}][segment]`" x-model="row.segment">
                                <option value="">All subscribed contacts</option>
                                <template x-for="s in (lists[row.list]?.segments ?? [])"><option :value="s.uid" x-text="s.name" :selected="s.uid === row.segment"></option></template>
                            </select>
                            <button type="button" class="btn-ghost btn-sm ml-auto text-red-600" @click="rows.splice(i, 1)"><x-icon name="x" class="size-4" /></button>
                        </div>
                    </template>
                    <button type="button" class="btn-secondary btn-sm" @click="rows.push({ list: Object.keys(lists)[0], segment: '', exclude: {{ $exclude ? 'true' : 'false' }} })"><x-icon name="plus" class="size-4" /> Add list</button>
                </div>
            @endforeach
            <p class="text-sm text-gray-500">Only subscribed contacts receive campaigns. Addresses on the blacklist and duplicates across lists are removed automatically.</p>
            <div class="flex justify-end"><button class="btn-primary">Save & continue</button></div>
        </form>
    @endif
</x-layouts.app>

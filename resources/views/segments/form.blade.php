<x-layouts.app :title="$segment->exists ? 'Edit segment' : 'New segment'">
    <x-page-header :title="$segment->exists ? $segment->name : 'Create a segment'" :subtitle="$list->name" :back="route('lists.segments.index', $list)" />

    <form method="POST" action="{{ $segment->exists ? route('lists.segments.update', [$list, $segment]) : route('lists.segments.store', $list) }}" class="max-w-4xl"
          x-data="segmentBuilder(@js(old('conditions', $segment->conditions ?: [['field' => 'EMAIL', 'operator' => 'contains', 'value' => '']])), @js(old('matching', $segment->matching)))" x-init="refresh()">
        @csrf
        @if ($segment->exists) @method('PUT') @endif
        <div class="card card-body space-y-5">
            <x-field name="name" label="Segment name" :value="$segment->name" required />
            <div class="flex items-center gap-2 text-sm">
                Match subscribers who meet
                <select name="matching" x-model="matching" @change="refresh()" class="input w-auto py-1.5"><option value="all">all</option><option value="any">any</option></select>
                of these conditions:
            </div>

            <template x-for="(c, i) in conditions" :key="i">
                <div class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 p-3">
                    <select class="input w-48" :name="`conditions[${i}][field]`" x-model="c.field" @change="c.operator = Object.keys(ops(c.field))[0]; c.value = ''; refresh()">
                        <template x-for="(f, key) in fields"><option :value="key" x-text="f.label" :selected="key === c.field"></option></template>
                    </select>
                    <select class="input w-48" :name="`conditions[${i}][operator]`" x-model="c.operator" @change="refresh()">
                        <template x-for="(label, key) in ops(c.field)"><option :value="key" x-text="label" :selected="key === c.operator"></option></template>
                    </select>
                    <template x-if="kind(c.field) === 'status'">
                        <select class="input w-44" :name="`conditions[${i}][value]`" x-model="c.value" @change="refresh()">
                            <template x-for="s in statuses"><option :value="s" x-text="s" :selected="s === c.value"></option></template>
                        </select>
                    </template>
                    <template x-if="kind(c.field) === 'verification'">
                        <select class="input w-44" :name="`conditions[${i}][value]`" x-model="c.value" @change="refresh()">
                            <template x-for="s in verifications"><option :value="s" x-text="s" :selected="s === c.value"></option></template>
                        </select>
                    </template>
                    <template x-if="kind(c.field) === 'date'"><input type="date" class="input w-44" :name="`conditions[${i}][value]`" x-model="c.value" @change="refresh()"></template>
                    <template x-if="kind(c.field) === 'activity'"><input type="number" min="1" class="input w-28" placeholder="days" :name="`conditions[${i}][value]`" x-model="c.value" @input.debounce.500ms="refresh()"></template>
                    <template x-if="(kind(c.field) === 'text' && !['blank', 'not_blank'].includes(c.operator)) || kind(c.field) === 'tag'">
                        <input class="input w-56" :name="`conditions[${i}][value]`" x-model="c.value" @input.debounce.500ms="refresh()">
                    </template>
                    <button type="button" class="btn-ghost btn-sm ml-auto text-red-600" @click="conditions.splice(i, 1); refresh()"><x-icon name="x" class="size-4" /></button>
                </div>
            </template>
            <button type="button" class="btn-secondary btn-sm" @click="conditions.push({ field: 'EMAIL', operator: 'contains', value: '' })"><x-icon name="plus" class="size-4" /> Add condition</button>

            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                <span class="text-sm text-gray-600">Matching subscribers: <strong class="tabular-nums text-gray-900" x-text="count === null ? '…' : count.toLocaleString()"></strong></span>
                <button class="btn-primary">Save segment</button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function segmentBuilder(conditions, matching) {
                return {
                    conditions, matching, count: null,
                    fields: @js($fields), operators: @js($operators), statuses: @js($statuses), verifications: @js($verifications),
                    kind(field) { return this.fields[field]?.kind ?? 'text' },
                    ops(field) { return this.operators[this.kind(field)] },
                    async refresh() {
                        const res = await fetch(@js(route('lists.segments.count', $list)), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ conditions: this.conditions, matching: this.matching }),
                        });
                        this.count = (await res.json()).count;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>

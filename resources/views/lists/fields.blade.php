<x-layouts.app :title="$list->name.' fields'">
    <x-page-header :title="$list->name" :back="route('lists.index')" />
    @include('lists._tabs')

    @php
        $rows = old('fields', $fields->map(fn ($f) => [
            'id' => $f->id, 'label' => $f->label, 'tag' => $f->tag, 'type' => $f->type,
            'options' => implode("\n", $f->options ?? []), 'default_value' => $f->default_value,
            'required' => $f->required, 'visible' => $f->visible,
        ])->values()->all());
    @endphp

    <form method="POST" action="{{ route('lists.fields.update', $list) }}" class="max-w-4xl"
          x-data="{ fields: @js($rows), add() { this.fields.push({ id: null, label: '', tag: '', type: 'text', options: '', default_value: '', required: false, visible: true }) }, move(i, d) { const f = this.fields.splice(i, 1)[0]; this.fields.splice(i + d, 0, f) } }">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Subscriber fields</h2>
                    <p class="text-xs text-gray-500">Use a field in emails with its merge tag, e.g. <code>&#123;&#123; SUBSCRIBER_FIRST_NAME &#125;&#125;</code>.</p>
                </div>
                <button type="button" class="btn-secondary btn-sm" @click="add()"><x-icon name="plus" class="size-4" /> Add field</button>
            </div>
            <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm">
                <span class="font-medium text-gray-900">Email</span><span class="badge-gray">Text</span><code class="text-xs text-gray-500">&#123;&#123; SUBSCRIBER_EMAIL &#125;&#125;</code><span class="badge-blue">Required</span>
            </div>
            <template x-for="(field, i) in fields" :key="i">
                <div class="grid gap-3 border-b border-gray-100 px-5 py-4 sm:grid-cols-12">
                    <input type="hidden" :name="`fields[${i}][id]`" :value="field.id">
                    <div class="sm:col-span-3"><label class="label">Label</label><input class="input" :name="`fields[${i}][label]`" x-model="field.label" required></div>
                    <div class="sm:col-span-3"><label class="label">Merge tag</label><input class="input font-mono uppercase" :name="`fields[${i}][tag]`" x-model="field.tag" placeholder="AUTO"></div>
                    <div class="sm:col-span-2"><label class="label">Type</label>
                        <select class="input" :name="`fields[${i}][type]`" x-model="field.type">
                            @foreach (\App\Models\ListField::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="label">Default</label><input class="input" :name="`fields[${i}][default_value]`" x-model="field.default_value"></div>
                    <div class="flex items-end justify-end gap-1 sm:col-span-2">
                        <button type="button" class="btn-ghost btn-sm" @click="move(i, -1)" :disabled="i === 0" title="Move up">↑</button>
                        <button type="button" class="btn-ghost btn-sm" @click="move(i, 1)" :disabled="i === fields.length - 1" title="Move down">↓</button>
                        <button type="button" class="btn-ghost btn-sm text-red-600" @click="fields.splice(i, 1)" title="Remove"><x-icon name="trash" class="size-4" /></button>
                    </div>
                    <div class="sm:col-span-12" x-show="field.type === 'dropdown'"><label class="label">Options (one per line)</label><textarea class="input" rows="3" :name="`fields[${i}][options]`" x-model="field.options"></textarea></div>
                    <div class="flex gap-5 text-sm sm:col-span-12">
                        <label class="flex items-center gap-2"><input type="checkbox" class="rounded border-gray-300 text-brand-600" :name="`fields[${i}][required]`" value="1" x-model="field.required"> Required</label>
                        <label class="flex items-center gap-2"><input type="checkbox" class="rounded border-gray-300 text-brand-600" :name="`fields[${i}][visible]`" value="1" x-model="field.visible"> Show on signup forms</label>
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-4 flex justify-end"><button class="btn-primary">Save fields</button></div>
    </form>
</x-layouts.app>

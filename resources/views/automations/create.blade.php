<x-layouts.app :title="$automation->exists ? 'Automation settings' : 'New automation'">
    <x-page-header :title="$automation->exists ? 'Settings · '.$automation->name : 'Create an automation'"
        :back="$automation->exists ? route('automations.show', $automation) : route('automations.index')" />

    @if ($lists->isEmpty())
        <div class="card"><x-empty title="Create a list first" icon="users">Automations run on the contacts of a list.
            <x-slot:action><a href="{{ route('lists.create') }}" class="btn-primary">Create list</a></x-slot:action></x-empty></div>
    @else
        @php
            $listFields = $lists->mapWithKeys(fn ($l) => [$l->uid => $l->fields->map(fn ($f) => ['tag' => $f->tag, 'label' => $f->label, 'type' => $f->type])->values()]);
            $config = old('config', $automation->trigger_config ?? []);
        @endphp
        <form method="POST" action="{{ $automation->exists ? route('automations.settings.update', $automation) : route('automations.store') }}" class="max-w-4xl space-y-6"
              x-data="{ trigger: @js(old('trigger_type', $automation->trigger_type)), list: @js(old('list', $automation->list?->uid ?? $lists->first()->uid)), fields: @js($listFields), triggers: @js(collect(\App\Models\Automation::TRIGGERS)->map(fn ($t) => $t['config'])) }">
            @csrf
            @if ($automation->exists) @method('PUT') @endif

            <div class="card card-body space-y-4">
                <x-field name="name" label="Automation name" :value="$automation->name" required placeholder="e.g. Welcome series" />
                <div>
                    <label class="label">List</label>
                    <select name="list" class="input" x-model="list">
                        @foreach ($lists as $list)<option value="{{ $list->uid }}">{{ $list->name }}</option>@endforeach
                    </select>
                    @error('list')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card card-body">
                <h2 class="card-title">What starts this automation?</h2>
                <input type="hidden" name="trigger_type" :value="trigger">
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Models\Automation::TRIGGERS as $key => $trigger)
                        <button type="button" @click="trigger = @js($key)" class="rounded-lg border p-3.5 text-left transition"
                                :class="trigger === @js($key) ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' : 'border-gray-200 hover:border-gray-300'">
                            <span class="block text-sm font-semibold text-gray-900">{{ $trigger['label'] }}</span>
                            <span class="mt-1 block text-xs text-gray-500">{{ $trigger['description'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                    <div x-show="triggers[trigger].includes('field')" x-cloak>
                        <label class="label">Field</label>
                        <select name="config[field]" class="input">
                            <template x-for="f in fields[list]"><option :value="f.tag" x-text="f.label + (f.type === 'date' ? ' (date)' : '')" :selected="f.tag === @js($config['field'] ?? '')"></option></template>
                        </select>
                        <p class="help" x-show="['birthday', 'date_field'].includes(trigger)">Use a date field (YYYY-MM-DD). Add one under the list's Fields tab.</p>
                        @error('config.field')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="triggers[trigger].includes('tag')" x-cloak><x-field name="config[tag]" label="Tag" :value="$config['tag'] ?? ''" /></div>
                    <div x-show="triggers[trigger].includes('value')" x-cloak><x-field name="config[value]" label="Changes to" :value="$config['value'] ?? '*'" help="Use * for any value, e.g. gold* matches gold and golden." /></div>
                    <div x-show="triggers[trigger].includes('offset_days')" x-cloak><x-field name="config[offset_days]" type="number" label="Days after the date" :value="$config['offset_days'] ?? 0" help="Negative = before (e.g. -3 sends three days before)." /></div>
                    <div x-show="triggers[trigger].includes('weekday')" x-cloak>
                        <label class="label">Day of the week</label>
                        <select name="config[weekday]" class="input">
                            @foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $num => $day)
                                <option value="{{ $num }}" @selected(($config['weekday'] ?? 1) == $num)>{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="triggers[trigger].includes('monthday')" x-cloak><x-field name="config[monthday]" type="number" min="1" max="31" label="Day of the month" :value="$config['monthday'] ?? 1" help="31 means the last day in shorter months." /></div>
                    <div x-show="triggers[trigger].includes('time')" x-cloak><x-field name="config[time]" type="time" label="Time of day" :value="$config['time'] ?? '09:00'" :help="'In the client\'s timezone ('.$currentWorkspace->timezone.').'" /></div>
                </div>
                <div class="mt-5 border-t border-gray-100 pt-5" x-show="['subscribed', 'tag_added', 'tag_removed', 'field_updated'].includes(trigger)">
                    <x-toggle name="allow_reentry" label="Allow contacts to go through again" help="Otherwise each contact enters this automation only once." :checked="$automation->allow_reentry" />
                </div>
            </div>

            <div class="flex justify-end"><button class="btn-primary">{{ $automation->exists ? 'Save settings' : 'Create automation' }}</button></div>
        </form>
    @endif
</x-layouts.app>

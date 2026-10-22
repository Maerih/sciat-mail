<x-layouts.app :title="'Edit step · '.$automation->name">
    <x-page-header :title="\App\Services\Automation\Flow::TYPES[$step['type']]" :subtitle="$automation->name" :back="route('automations.show', $automation)" />

    <form method="POST" action="{{ route('automations.step.update', [$automation, $stepId]) }}" class="card card-body max-w-2xl space-y-4">
        @csrf @method('PUT')

        @if ($step['type'] === 'wait')
            <p class="text-sm text-gray-600">Hold the contact here before moving to the next step.</p>
            <div class="flex gap-3">
                <x-field name="amount" type="number" min="0" label="Wait for" :value="$step['amount'] ?? 1" class="w-32" />
                <div class="flex-1">
                    <label class="label">&nbsp;</label>
                    <select name="unit" class="input">
                        @foreach (\App\Services\Automation\Flow::WAIT_UNITS as $value => $label)<option value="{{ $value }}" @selected(($step['unit'] ?? 'days') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>

        @elseif ($step['type'] === 'condition')
            @php $rule = old() ? old() : ($step['rule'] ?? []); @endphp
            <div x-data="{ kind: @js($rule['kind'] ?? 'opened') }" class="space-y-4">
                <p class="text-sm text-gray-600">Contacts who match continue on the <strong>Yes</strong> path, everyone else on the <strong>No</strong> path. Put a Wait step before an opened/clicked check so people have time to read.</p>
                <div>
                    <label class="label">Check whether the contact…</label>
                    <select name="kind" class="input" x-model="kind">
                        @foreach (\App\Services\Automation\Flow::CONDITIONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div x-show="kind === 'opened' || kind === 'clicked'" x-cloak>
                    <label class="label">Email</label>
                    <select name="step" class="input">
                        <option value="">Any email in this automation</option>
                        @foreach ($sendSteps as $id => $send)
                            <option value="{{ $id }}" @selected(($rule['step'] ?? '') === $id)>{{ $emails->get($send['campaign_id'])?->name ?? 'Email' }} — {{ $emails->get($send['campaign_id'])?->subject ?: 'no subject' }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="kind === 'tag'" x-cloak><x-field name="tag" label="Tag" :value="$rule['tag'] ?? ''" /></div>
                <div x-show="kind === 'field'" x-cloak class="grid gap-3 sm:grid-cols-3">
                    <div><label class="label">Field</label>
                        <select name="field" class="input">
                            <option value="EMAIL">Email</option>
                            @foreach ($automation->list->fields as $field)<option value="{{ $field->tag }}" @selected(($rule['field'] ?? '') === $field->tag)>{{ $field->label }}</option>@endforeach
                        </select></div>
                    <div><label class="label">Operator</label>
                        <select name="operator" class="input">
                            @foreach ($operators as $value => $label)<option value="{{ $value }}" @selected(($rule['operator'] ?? 'equal') === $value)>{{ $label }}</option>@endforeach
                        </select></div>
                    <x-field name="value" label="Value" :value="$rule['value'] ?? ''" />
                </div>
            </div>

        @elseif ($step['type'] === 'sms')
            <p class="text-sm text-[var(--muted)]">Texts the contact's phone number (the list's phone field). Contacts without a number, or who replied STOP, are skipped. Tip: put this on the “No” path of an “Opened an email” condition to follow up with people who ignored your email.</p>
            @include('sms._composer', ['name' => 'message', 'value' => old('message', $step['message'] ?? ''), 'tags' => \App\Sending\MergeTags::catalogue($automation->list)])
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">SMS provider</label>
                    <select name="provider_id" class="input">
                        <option value="">Default provider</option>
                        @foreach ($smsProviders as $provider)<option value="{{ $provider->id }}" @selected(($step['provider_id'] ?? null) == $provider->id)>{{ $provider->name }} ({{ $provider->typeLabel() }})</option>@endforeach
                    </select>
                    @if ($smsProviders->isEmpty())<p class="help text-amber-600">No SMS provider yet — <a href="{{ route('sms.providers.index') }}" class="underline">add one</a>.</p>@endif
                </div>
                <x-field name="sender" label="Sender ID (optional)" :value="$step['sender'] ?? ''" help="Leave blank to use the provider's default." />
            </div>

        @elseif ($step['type'] === 'action')
            <div x-data="{ action: @js(old('action', $step['action'] ?? 'add_tag')) }" class="space-y-4">
                <div>
                    <label class="label">Action</label>
                    <select name="action" class="input" x-model="action">
                        @foreach (\App\Services\Automation\Flow::ACTIONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div x-show="action === 'add_tag' || action === 'remove_tag'" x-cloak><x-field name="tag" label="Tag" :value="$step['tag'] ?? ''" /></div>
                <div x-show="action === 'update_field'" x-cloak class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Field</label>
                        <select name="field" class="input">
                            @foreach ($automation->list->fields as $field)<option value="{{ $field->tag }}" @selected(($step['field'] ?? '') === $field->tag)>{{ $field->label }}</option>@endforeach
                        </select></div>
                    <x-field name="value" label="New value" :value="$step['value'] ?? ''" />
                </div>
                <div x-show="action === 'copy_list' || action === 'move_list'" x-cloak>
                    <label class="label">Target list</label>
                    <select name="list_id" class="input">
                        @foreach ($lists as $list)<option value="{{ $list->id }}" @selected(($step['list_id'] ?? null) == $list->id)>{{ $list->name }}</option>@endforeach
                    </select>
                    <p class="help" x-show="action === 'move_list'">Moving unsubscribes the contact from this list and ends their journey here.</p>
                </div>
                <p class="help" x-show="action === 'unsubscribe'">The contact is unsubscribed from {{ $automation->list->name }} and leaves the automation.</p>
            </div>
        @endif

        <div class="flex justify-between pt-2">
            <a href="{{ route('automations.show', $automation) }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save step</button>
        </div>
    </form>
</x-layouts.app>

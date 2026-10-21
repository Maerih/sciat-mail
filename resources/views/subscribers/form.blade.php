<x-layouts.app :title="$subscriber->exists ? 'Edit subscriber' : 'Add subscriber'">
    <x-page-header :title="$subscriber->exists ? $subscriber->email : 'Add a subscriber'" :subtitle="$list->name" :back="route('lists.subscribers.index', $list)" />

    <form method="POST" action="{{ $subscriber->exists ? route('lists.subscribers.update', [$list, $subscriber]) : route('lists.subscribers.store', $list) }}" class="card card-body max-w-2xl space-y-4">
        @csrf
        @if ($subscriber->exists) @method('PUT') @endif
        <x-field name="EMAIL" type="email" label="Email" :value="$subscriber->email" required />
        @foreach ($list->fields as $field)
            @php $value = old($field->tag, $subscriber->field($field->tag)); @endphp
            @if ($field->type === 'dropdown')
                <div>
                    <label class="label">{{ $field->label }} @if ($field->required)<span class="text-red-500">*</span>@endif</label>
                    <select name="{{ $field->tag }}" class="input">
                        <option value=""></option>
                        @foreach ($field->options ?? [] as $option)<option @selected($value === $option)>{{ $option }}</option>@endforeach
                    </select>
                    @error($field->tag)<p class="error">{{ $message }}</p>@enderror
                </div>
            @elseif ($field->type === 'checkbox')
                <x-toggle :name="$field->tag" :label="$field->label" :checked="(bool) $value" />
            @else
                <x-field :name="$field->tag" :label="$field->label" :type="['number' => 'number', 'date' => 'date'][$field->type] ?? 'text'" :value="$value" :required="$field->required" />
            @endif
        @endforeach
        <x-field name="tags" label="Tags" :value="implode(', ', $subscriber->tags ?? [])" help="Comma separated." />

        @if ($subscriber->exists)
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    @foreach (\App\Models\Subscriber::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $subscriber->status) === $status)>{{ ucfirst($status) }}</option>@endforeach
                </select>
            </div>
        @elseif ($list->double_optin)
            <x-toggle name="skip_confirmation" label="Skip double opt-in" help="Only if this person already gave consent — otherwise a confirmation email is sent." />
        @endif

        <div class="flex items-center justify-between pt-2">
            @if ($subscriber->exists)
                <x-confirm-delete :action="route('lists.subscribers.destroy', [$list, $subscriber])" label="Delete" class="btn-ghost text-red-600 hover:bg-red-50" />
            @else
                <span></span>
            @endif
            <button class="btn-primary">{{ $subscriber->exists ? 'Save' : 'Add subscriber' }}</button>
        </div>
    </form>
</x-layouts.app>

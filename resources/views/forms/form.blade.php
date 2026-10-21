<x-layouts.app :title="$form->exists ? 'Edit form' : 'New form'">
    <x-page-header :title="$form->exists ? $form->name : 'Create a signup form'" :back="route('forms.index')" />
    @if ($lists->isEmpty())
        <div class="card"><x-empty title="Create a list first" icon="users">Forms add subscribers to a list.
            <x-slot:action><a href="{{ route('lists.create') }}" class="btn-primary">Create list</a></x-slot:action></x-empty></div>
    @else
        <form method="POST" action="{{ $form->exists ? route('forms.update', $form) : route('forms.store') }}" class="card card-body max-w-2xl space-y-4" x-data="{ consent: @js((bool) old('consent_enabled', $form->consent_enabled)) }">
            @csrf
            @if ($form->exists) @method('PUT') @endif
            <x-field name="name" label="Internal name" :value="$form->name" required />
            <div>
                <label class="label">Adds subscribers to</label>
                <select name="list" class="input" required>
                    @foreach ($lists as $list)<option value="{{ $list->uid }}" @selected(old('list', $selectedList) === $list->uid)>{{ $list->name }}{{ $list->double_optin ? ' (double opt-in)' : '' }}</option>@endforeach
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="title" label="Form heading" :value="$form->title" />
                <x-field name="button_text" label="Button text" :value="$form->button_text" required />
            </div>
            <x-field name="success_message" type="textarea" rows="2" label="Thank-you message" :value="$form->success_message" placeholder="Thank you for subscribing!" />
            <x-field name="redirect_url" type="url" label="Redirect after submit (optional)" :value="$form->redirect_url" help="Send people to your own thank-you page instead." />
            <x-toggle name="only_required_fields" label="Show only required fields" :checked="$form->only_required_fields" />
            <x-toggle name="consent_enabled" label="Require a consent checkbox (GDPR)" :checked="$form->consent_enabled" x-model="consent" />
            <div x-show="consent" x-cloak><x-field name="consent_text" type="textarea" rows="2" label="Consent text (HTML links allowed)" :value="$form->consent_text" /></div>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input"><option value="published" @selected($form->status === 'published')>Published</option><option value="draft" @selected($form->status === 'draft')>Draft (submissions rejected)</option></select>
            </div>
            <div class="flex justify-end"><button class="btn-primary">{{ $form->exists ? 'Save form' : 'Create form' }}</button></div>
        </form>
    @endif
</x-layouts.app>

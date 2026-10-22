<x-layouts.app :title="$form->exists ? 'Edit form' : 'New form'">
    <x-page-header :title="$form->exists ? $form->name : 'Create a signup form'" :back="route('forms.index')" />
    @if ($lists->isEmpty())
        <div class="card"><x-empty title="Create a list first" icon="users">Forms add subscribers to a list.
            <x-slot:action><a href="{{ route('lists.create') }}" class="btn-primary">Create list</a></x-slot:action></x-empty></div>
    @else
        <form method="POST" action="{{ $form->exists ? route('forms.update', $form) : route('forms.store') }}" class="card card-body max-w-2xl space-y-4" x-data="{ consent: @js((bool) old('consent_enabled', $form->consent_enabled)), display: @js(old('display', $form->display ?? 'embedded')), trigger: @js(old('popup.trigger', $form->popup('trigger'))) }">
            @csrf
            @if ($form->exists) @method('PUT') @endif
            <x-field name="name" label="Internal name" :value="$form->name" required />
            <div>
                <label class="label">Display as</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['embedded' => ['Embedded form', 'Placed inside the page where you paste the code.'], 'popup' => ['Popup', 'Appears over the page — modal, slide-in or bar.']] as $value => [$label, $hint])
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="display === @js($value) ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                            <input type="radio" name="display" value="{{ $value }}" x-model="display" class="mt-0.5 text-brand-600">
                            <span><span class="block text-sm font-medium">{{ $label }}</span><span class="text-xs text-gray-500">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div x-show="display === 'popup'" x-cloak class="grid gap-4 rounded-lg bg-gray-50 p-4 sm:grid-cols-2">
                <div>
                    <label class="label">Show</label>
                    <select name="popup[trigger]" class="input" x-model="trigger">
                        @foreach (\App\Models\Form::TRIGGERS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div x-show="trigger === 'delay'"><x-field name="popup[delay]" type="number" min="0" label="Delay (seconds)" :value="$form->popup('delay')" /></div>
                <div x-show="trigger === 'scroll'"><x-field name="popup[scroll]" type="number" min="1" max="100" label="Scroll depth (%)" :value="$form->popup('scroll')" /></div>
                <div>
                    <label class="label">Position</label>
                    <select name="popup[position]" class="input">
                        @foreach (\App\Models\Form::POSITIONS as $value => $label)<option value="{{ $value }}" @selected($form->popup('position') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <x-field name="popup[frequency_days]" type="number" min="0" label="Don't show again for (days)" :value="$form->popup('frequency_days')" help="0 = every page view. Subscribers never see it again." />
                <x-field name="popup[color]" type="color" label="Button colour" :value="$form->popup('color')" class="[&_input]:h-10 [&_input]:p-1" />
                <x-field name="popup[description]" type="textarea" rows="2" label="Short pitch under the heading" :value="$form->popup('description')" class="sm:col-span-2" placeholder="Get 10% off your first order and weekly tips." />
            </div>
            <div>
                <label class="label">Adds subscribers to</label>
                <select name="list" class="input" required>
                    @foreach ($lists as $list)<option value="{{ $list->uid }}" @selected(old('list', $selectedList) === $list->uid)>{{ $list->name }}{{ $list->double_optin ? ' (double opt-in)' : '' }}</option>@endforeach
                </select>
            </div>
            @if ($websites->isNotEmpty())
                <div>
                    <label class="label">Website</label>
                    <select name="website" class="input">
                        <option value="">Not connected — embed the code manually</option>
                        @foreach ($websites as $site)<option value="{{ $site->uid }}" @selected(old('website', $form->website?->uid ?? $selectedWebsite) === $site->uid)>{{ $site->name }} ({{ $site->host }})</option>@endforeach
                    </select>
                    <p class="help">Connected forms load through the website's tracking code — popups show automatically.</p>
                </div>
            @endif
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

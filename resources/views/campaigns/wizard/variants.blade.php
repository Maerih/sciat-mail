<x-layouts.app :title="'Variants · '.$campaign->name">
    @include('campaigns.wizard._layout')
    @php
        $rows = old('variants', $variants->map(fn ($v) => $v->only(['subject', 'from_name', 'preheader', 'html']))->values()->all());
        $rows = $rows ?: [['subject' => '', 'from_name' => '', 'preheader' => '', 'html' => ''], ['subject' => '', 'from_name' => '', 'preheader' => '', 'html' => '']];
    @endphp
    <form method="POST" action="{{ route('campaigns.variants.save', $campaign) }}" class="max-w-4xl space-y-6"
          x-data="{ variants: @js($rows), letters: 'ABCDE', aiLoading: false, aiError: '' }">
        @csrf
        <div class="card card-body space-y-2 text-sm text-gray-600">
            <h2 class="card-title">How the test works</h2>
            <p>Each variant overrides only what you fill in — empty fields fall back to the campaign's setup and default content
               (subject “{{ $campaign->subject }}”, from {{ $campaign->from_name }}). Test one thing at a time for a clear answer.</p>
        </div>

        <template x-for="(v, i) in variants" :key="i">
            <div class="card card-body space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Variant <span x-text="letters[i]"></span></h3>
                    <button type="button" class="btn-ghost btn-sm text-red-600" x-show="variants.length > 2" @click="variants.splice(i, 1)">Remove</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label">Subject line</label><input class="input" :name="`variants[${i}][subject]`" x-model="v.subject" placeholder="{{ $campaign->subject }}"></div>
                    <div><label class="label">From name</label><input class="input" :name="`variants[${i}][from_name]`" x-model="v.from_name" placeholder="{{ $campaign->from_name }}"></div>
                    <div><label class="label">Preheader</label><input class="input" :name="`variants[${i}][preheader]`" x-model="v.preheader" placeholder="{{ $campaign->preheader }}"></div>
                </div>
                <details :open="!!v.html">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700">Different content for this variant</summary>
                    <textarea class="input mt-2 font-mono text-xs" rows="8" :name="`variants[${i}][html]`" x-model="v.html" placeholder="Leave empty to use the default content"></textarea>
                </details>
            </div>
        </template>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-secondary btn-sm" x-show="variants.length < 5" @click="variants.push({ subject: '', from_name: '', preheader: '', html: '' })"><x-icon name="plus" class="size-4" /> Add variant</button>
            @if (\App\Services\Ai\AiAssistant::isConfigured())
                <button type="button" class="btn-ghost btn-sm text-brand-700" :disabled="aiLoading" @click="aiLoading = true; aiError = ''; aiPost(@js(route('ai.subjects')), { subject: @js($campaign->subject), content: @js((string) $campaign->html), count: variants.length })
                    .then(d => d.suggestions.forEach((s, i) => { if (variants[i]) { variants[i].subject = s.subject; variants[i].preheader = s.preheader } }))
                    .catch(e => aiError = e.message).finally(() => aiLoading = false)">
                    <x-icon name="bolt" class="size-4" /> <span x-text="aiLoading ? 'Writing…' : 'Fill subjects with AI (different angles)'"></span>
                </button>
                <span class="error" x-show="aiError" x-text="aiError"></span>
            @endif
        </div>

        <div class="card card-body grid gap-4 sm:grid-cols-3">
            <h2 class="card-title sm:col-span-3">Test settings</h2>
            <x-field name="test_percent" type="number" min="5" max="100" label="Test group size (%)" :value="$campaign->abSetting('test_percent')" help="Split evenly across variants. 100% = everyone, no winner phase." />
            <div>
                <label class="label">Pick the winner by</label>
                <select name="metric" class="input">
                    <option value="opens" @selected($campaign->abSetting('metric') === 'opens')>Open rate</option>
                    <option value="clicks" @selected($campaign->abSetting('metric') === 'clicks')>Click rate</option>
                </select>
                <p class="help">Clicks are more reliable — Apple Mail inflates opens.</p>
            </div>
            <x-field name="wait_hours" type="number" min="1" max="168" label="Test duration (hours)" :value="$campaign->abSetting('wait_hours')" help="Then the winner goes to everyone else." />
        </div>

        <div class="flex justify-between">
            <a href="{{ route('campaigns.content', $campaign) }}" class="btn-secondary">Back</a>
            <button class="btn-primary">Save & continue</button>
        </div>
    </form>
</x-layouts.app>

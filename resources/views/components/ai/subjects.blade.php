@props(['subject' => '#f-subject', 'preheader' => null, 'content' => '', 'label' => 'Suggest with AI'])
@if (\App\Services\Ai\AiAssistant::isConfigured())
<div class="relative" x-data="aiSubjects(@js(route('ai.subjects')), { subjectInput: @js($subject), preheaderInput: @js($preheader), content: @js((string) $content) })">
    <button type="button" class="btn-ghost btn-sm text-brand-700" @click="generate()"><x-icon name="bolt" class="size-4" /> {{ $label }}</button>
    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-[28rem] max-w-[90vw] rounded-xl border border-gray-200 bg-white p-2 shadow-xl">
        <div x-show="loading" class="px-3 py-6 text-center text-sm text-gray-500">Writing subject lines…</div>
        <div x-show="error" class="px-3 py-3 text-sm text-red-600" x-text="error"></div>
        <template x-for="s in suggestions">
            <button type="button" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-brand-50" @click="use(s)">
                <span class="badge-purple mr-1 align-middle" x-text="s.angle"></span>
                <span class="text-sm font-medium text-gray-900" x-text="s.subject"></span>
                <span class="mt-0.5 block text-xs text-gray-500" x-text="s.preheader"></span>
            </button>
        </template>
        <div x-show="!loading && suggestions.length" class="flex justify-between border-t border-gray-100 px-2 pt-2 text-xs">
            <span class="text-gray-400">Click one to use it</span>
            <button type="button" class="text-brand-600 hover:underline" @click="generate()">Try again</button>
        </div>
    </div>
</div>
@endif

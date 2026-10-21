<x-layouts.app :title="'Email · '.$automation->name">
    <x-page-header :title="$campaign->name" :subtitle="'Send email step · '.$automation->name" :back="route('automations.show', $automation)">
        <x-slot:actions><a href="{{ route('campaigns.preview', $campaign) }}" target="_blank" class="btn-secondary"><x-icon name="eye" /> Preview</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('automations.email.update', [$automation, $stepId]) }}"
          x-data="{ html: @js(old('html', $campaign->html ?? '')), plain: @js(old('plain', $campaign->plain ?? '')), gallery: false,
                    insert(tag) { const el = this.$refs.editor; const v = '{'.repeat(2) + ' ' + tag + ' ' + '}'.repeat(2); el.setRangeText(v, el.selectionStart, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input')); el.focus() } }">
        @csrf @method('PUT')
        <div class="card card-body mb-6 grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Internal name" :value="$campaign->name" required />
            <x-field name="subject" label="Subject line" :value="$campaign->subject" required />
            <x-field name="preheader" label="Preheader" :value="$campaign->preheader" class="sm:col-span-2" />
            <x-field name="from_name" label="From name" :value="$campaign->from_name" required />
            <x-field name="from_email" type="email" label="From email" :value="$campaign->from_email" required />
            <x-field name="reply_to" type="email" label="Reply-to" :value="$campaign->reply_to" />
            <div class="flex items-end gap-6 pb-2">
                <x-toggle name="track_open" label="Track opens" :checked="$campaign->track_open" />
                <x-toggle name="track_click" label="Track clicks" :checked="$campaign->track_click" />
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="card flex flex-col">
                <div class="card-header">
                    <h2 class="card-title">HTML</h2>
                    <div class="flex gap-2">
                        <a href="{{ route('campaigns.builder', $campaign) }}" class="btn-primary btn-sm" onclick="return confirm('Unsaved changes on this page will be lost. Open the builder?')">Drag & drop builder</a>
                        <button type="button" class="btn-secondary btn-sm" @click="gallery = true"><x-icon name="template" class="size-4" /> Templates</button>
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" class="btn-secondary btn-sm" @click="open = !open">Merge tags <x-icon name="chevron-down" class="size-3.5" /></button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-10 mt-1 max-h-72 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                                @foreach ($tags as $tag => $label)
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="insert(@js($tag)); open = false"><span class="block">{{ $label }}</span><code class="text-xs text-gray-500">{{ $tag }}</code></button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <textarea x-ref="editor" name="html" x-model="html" spellcheck="false" class="min-h-[480px] w-full flex-1 resize-y border-0 bg-gray-950 p-4 font-mono text-xs text-gray-100 focus:ring-0"></textarea>
                <details class="border-t border-gray-100 px-5 py-3 text-sm">
                    <summary class="cursor-pointer font-medium text-gray-700">Plain-text version (optional — leave HTML empty to send plain text only)</summary>
                    <textarea name="plain" x-model="plain" rows="6" class="input mt-2 font-mono text-xs"></textarea>
                </details>
            </div>
            <div class="card flex flex-col">
                <div class="card-header"><h2 class="card-title">Live preview</h2></div>
                <iframe class="min-h-[480px] w-full flex-1 bg-white" sandbox="" :srcdoc="html"></iframe>
            </div>
        </div>
        @error('html')<p class="error mt-2">{{ $message }}</p>@enderror

        <div class="mt-6 flex justify-end gap-2">
            <button name="next" value="stay" class="btn-secondary">Save</button>
            <button class="btn-primary">Save & back to flow</button>
        </div>

        <div x-show="gallery" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:p-10" @keydown.escape.window="gallery = false">
            <div class="card w-full max-w-3xl" @click.outside="gallery = false">
                <div class="card-header"><h2 class="card-title">Choose a template</h2><button type="button" class="btn-ghost btn-sm" @click="gallery = false"><x-icon name="x" class="size-4" /></button></div>
                <div class="divide-y divide-gray-100">
                    @foreach ($templates as $template)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span><span class="block text-sm font-medium">{{ $template->name }}</span><span class="text-xs text-gray-500">{{ $template->isSystem() ? 'Starter' : 'My template' }}</span></span>
                            <span class="flex gap-2">
                                <a href="{{ route('templates.preview', $template) }}" target="_blank" class="btn-ghost btn-sm">Preview</a>
                                <button name="template" value="{{ $template->uid }}" class="btn-primary btn-sm" onclick="return !document.querySelector('[name=html]').value || confirm('Replace the current content?')">Use</button>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>

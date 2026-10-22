<x-layouts.app :title="'Content · '.$campaign->name">
    @include('campaigns.wizard._layout')

    <form method="POST" action="{{ route('campaigns.content.save', $campaign) }}" x-data="{ html: @js(old('html', $campaign->html ?? '')), plain: @js(old('plain', $campaign->plain ?? '')), gallery: @js(! $campaign->isPlain() && blank($campaign->html)), insert(tag) { const el = this.$refs.editor; const s = el.selectionStart; const v = '{'.repeat(2) + ' ' + tag + ' ' + '}'.repeat(2); el.setRangeText(v, s, el.selectionEnd, 'end'); el.dispatchEvent(new Event('input')); el.focus() } }">
        @csrf
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="card flex flex-col">
                <div class="card-header">
                    <h2 class="card-title">{{ $campaign->isPlain() ? 'Plain text message' : ($campaign->isAbTest() ? 'Default HTML (variants can override it)' : 'HTML code') }}</h2>
                    <div class="flex gap-2">
                        @if ($campaign->isPlain())
                            <x-ai.rewrite target="textarea[name=plain]" />
                        @else
                            <x-ai.draft on-draft="(d) => { const el = document.querySelector('textarea[name=html]'); el.value = d.html; el.dispatchEvent(new Event('input', { bubbles: true })); }" />
                        @endif
                        @unless ($campaign->isPlain())
                            <a href="{{ route('campaigns.builder', $campaign) }}" class="btn-primary btn-sm">Drag & drop builder</a>
                            <button type="button" class="btn-secondary btn-sm" @click="gallery = true"><x-icon name="template" class="size-4" /> Templates</button>
                        @endunless
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" class="btn-secondary btn-sm" @click="open = !open">Merge tags <x-icon name="chevron-down" class="size-3.5" /></button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-10 mt-1 max-h-72 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                                @foreach ($tags as $tag => $label)
                                    <button type="button" class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="insert(@js($tag)); open = false">
                                        <span class="block">{{ $label }}</span><code class="text-xs text-gray-500">{{ $tag }}</code>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @if ($campaign->isPlain())
                    <textarea x-ref="editor" name="plain" x-model="plain" class="min-h-[520px] w-full flex-1 resize-y border-0 p-4 font-mono text-sm focus:ring-0" placeholder="Hi &#123;&#123; SUBSCRIBER_FIRST_NAME | there &#125;&#125;,"></textarea>
                @else
                    <textarea x-ref="editor" name="html" x-model="html" spellcheck="false" class="min-h-[520px] w-full flex-1 resize-y border-0 code-editor p-4 font-mono text-xs focus:ring-0" placeholder="<html>…</html>"></textarea>
                    <details class="border-t border-gray-100 px-5 py-3 text-sm">
                        <summary class="cursor-pointer font-medium text-gray-700">Custom plain-text version (optional)</summary>
                        <textarea name="plain" x-model="plain" rows="6" class="input mt-2 font-mono text-xs" placeholder="Leave empty to generate it from the HTML automatically."></textarea>
                    </details>
                @endif
            </div>
            <div class="card flex flex-col">
                <div class="card-header"><h2 class="card-title">Live preview</h2><span class="text-xs text-gray-500">Merge tags render on send · footer added if missing</span></div>
                @if ($campaign->isPlain())
                    <pre class="min-h-[520px] flex-1 p-4 font-sans text-sm whitespace-pre-wrap" x-text="plain"></pre>
                @else
                    <iframe class="min-h-[520px] w-full flex-1 bg-white" sandbox="" :srcdoc="html"></iframe>
                @endif
            </div>
        </div>
        @error('html')<p class="error mt-2">{{ $message }}</p>@enderror
        @error('plain')<p class="error mt-2">{{ $message }}</p>@enderror

        <x-wizard-bar icon="palette" title="Design the email" text="Use merge tags to personalise, and preview before you continue.">
            <a href="{{ route('campaigns.setup', $campaign) }}" class="btn-secondary"><x-icon name="arrow_back" class="size-4.5" /> Back</a>
            <button name="next" value="stay" class="btn-secondary"><x-icon name="save" class="size-4.5" /> Save</button>
            <button class="btn-primary">Save & continue <x-icon name="arrow_forward" class="size-4.5" /></button>
        </x-wizard-bar>

        @unless ($campaign->isPlain())
            <div x-show="gallery" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto overlay p-4 sm:p-10" @keydown.escape.window="gallery = false">
                <div class="card w-full max-w-5xl" @click.outside="gallery = false">
                    <div class="card-header"><h2 class="card-title">Choose a template</h2><button type="button" class="btn-ghost btn-sm" @click="gallery = false"><x-icon name="x" class="size-4" /></button></div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($templates as $template)
                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                <div class="h-48 overflow-hidden bg-gray-50"><iframe src="{{ route('templates.preview', $template) }}" class="pointer-events-none h-[600px] w-[200%] origin-top-left scale-50 border-0" loading="lazy" sandbox=""></iframe></div>
                                <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-3 py-2.5">
                                    <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $template->name }}</span><span class="text-xs text-gray-500">{{ $template->isSystem() ? 'Starter' : 'My template' }}</span></span>
                                    <button type="submit" form="use-template" name="template" value="{{ $template->uid }}" class="btn-primary btn-sm" onclick="return !document.querySelector('[name=html]').value || confirm('Replace the current content with this template?')">Use</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endunless
    </form>
    <form id="use-template" method="POST" action="{{ route('campaigns.content.template', $campaign) }}">@csrf</form>
</x-layouts.app>

<x-layouts.app :title="$template->exists ? $template->name : 'New template'">
    <x-page-header :title="$template->exists ? 'Edit template' : 'New template'" :back="route('templates.index')" />
    <form method="POST" action="{{ $template->exists ? route('templates.update', $template) : route('templates.store') }}" x-data="{ html: @js(old('html', $template->html)) }">
        @csrf
        @if ($template->exists) @method('PUT') @else <input type="hidden" name="from" value="{{ request('from') }}"> @endif
        <div class="card card-body mb-4 max-w-xl"><x-field name="name" label="Template name" :value="$template->name" required /></div>
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="card flex flex-col">
                <div class="card-header"><h2 class="card-title">HTML</h2>
                    @if ($template->exists)<a href="{{ route('templates.builder', $template) }}" class="btn-primary btn-sm">Drag & drop builder</a>
                    @else<span class="text-xs text-gray-500">Save first to use the drag & drop builder</span>@endif</div>
                <textarea name="html" x-model="html" spellcheck="false" class="min-h-[560px] w-full flex-1 resize-y border-0 code-editor p-4 font-mono text-xs focus:ring-0"></textarea>
            </div>
            <div class="card flex flex-col">
                <div class="card-header"><h2 class="card-title">Preview</h2></div>
                <iframe class="min-h-[560px] w-full flex-1 bg-white" sandbox="" :srcdoc="html"></iframe>
            </div>
        </div>
        <div class="mt-4 flex justify-end"><button class="btn-primary">Save template</button></div>
    </form>
</x-layouts.app>

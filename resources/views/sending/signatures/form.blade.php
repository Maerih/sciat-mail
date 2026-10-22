<x-layouts.app :title="$signature->exists ? 'Edit signature' : 'New signature'">
    <x-page-header :title="$signature->exists ? 'Edit signature' : 'New signature'" :back="route('sending.signatures.index')" />
    <form method="POST" action="{{ $signature->exists ? route('sending.signatures.update', $signature) : route('sending.signatures.store') }}"
        x-data="{ html: @js(old('html', $signature->html)) }" class="grid gap-6 lg:grid-cols-2">
        @csrf
        @if ($signature->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="code" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Signature</h2></div></div>
            <div class="card-body space-y-4">
                <x-field name="name" label="Name" :value="$signature->name" required />
                <div>
                    <label class="label" for="f-html">HTML</label>
                    <textarea id="f-html" name="html" x-model="html" rows="14" class="input code-editor font-mono text-xs" spellcheck="false" required></textarea>
                    @error('html')<p class="error">{{ $message }}</p>@enderror
                    <p class="help">Use inline styles — email apps ignore stylesheets. Links and images need full URLs.</p>
                </div>
                <x-toggle name="is_default" label="Default signature" :help="'Used wherever an email contains '.e('{'.'{ SIGNATURE }'.'}').'.'" :checked="$signature->is_default" />
                <div class="flex justify-end gap-2">
                    <a href="{{ route('sending.signatures.index') }}" class="btn-secondary">Cancel</a>
                    <button class="btn-primary"><x-icon name="save" class="size-4.5" /> Save signature</button>
                </div>
            </div>
        </div>
        <div class="card flex flex-col">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="preview" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Preview</h2></div><span class="text-xs text-[var(--muted)]">Updates as you type</span></div>
            <div class="flex-1 rounded-b-xl bg-white p-4">
                <iframe class="h-full min-h-72 w-full" sandbox="" :srcdoc="'<!doctype html><body style=&quot;margin:0;font-family:Arial,sans-serif&quot;><p style=&quot;color:#6b7280;font-size:14px&quot;>Thanks for reading!</p>' + html + '</body>'"></iframe>
            </div>
        </div>
    </form>
</x-layouts.app>

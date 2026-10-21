<x-layouts.app :title="$form->name">
    <x-page-header :title="$form->name" :subtitle="'Adds subscribers to '.$form->list->name" :back="route('forms.index')">
        <x-slot:actions><a href="{{ route('forms.edit', $form) }}" class="btn-secondary">Edit form</a></x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <div class="card card-body" x-data="copy(@js($code))">
                <div class="mb-3 flex items-center justify-between"><h2 class="card-title">Embed code</h2>
                    <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied!' : 'Copy code'"></button></div>
                <p class="mb-3 text-sm text-gray-500">Paste this HTML into the client's website where the form should appear.</p>
                <pre class="code max-h-96 overflow-auto whitespace-pre-wrap">{{ $code }}</pre>
            </div>
            <div class="card card-body" x-data="copy(@js(route('subscribe.show', $form->uid)))">
                <div class="flex items-center justify-between"><h2 class="card-title">Hosted signup page</h2>
                    <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied!' : 'Copy link'"></button></div>
                <a href="{{ route('subscribe.show', $form->uid) }}" target="_blank" class="mt-2 block truncate text-sm text-brand-600 hover:underline">{{ route('subscribe.show', $form->uid) }}</a>
                <p class="help">Share it in social bios, QR codes or ads.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Preview</h2></div>
            <div class="card-body bg-gray-50">{!! $code !!}</div>
        </div>
    </div>
</x-layouts.app>

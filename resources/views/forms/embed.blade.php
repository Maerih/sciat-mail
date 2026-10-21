<x-layouts.app :title="$form->name">
    <x-page-header :title="$form->name" :subtitle="'Adds subscribers to '.$form->list->name" :back="route('forms.index')">
        <x-slot:actions><a href="{{ route('forms.edit', $form) }}" class="btn-secondary">Edit form</a></x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            @if ($form->isPopup())
                @php $snippet = '<script src="'.route('subscribe.popup', $form->uid).'" async></script>'; @endphp
                <div class="card card-body" x-data="copy(@js($snippet))">
                    <div class="mb-3 flex items-center justify-between"><h2 class="card-title">Popup script</h2>
                        <span class="flex gap-2">
                            <button type="button" class="btn-secondary btn-sm" onclick="window.smPopupPreview = true; document.getElementById('sm-popup-root')?.remove(); const s = document.createElement('script'); s.src = @js(route('subscribe.popup', $form->uid)).concat('?t=', Date.now()); document.body.appendChild(s);">Preview popup</button>
                            <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied!' : 'Copy script'"></button>
                        </span></div>
                    <p class="mb-3 text-sm text-gray-500">Paste this once before <code>&lt;/body&gt;</code> on every page of the client's website. {{ \App\Models\Form::TRIGGERS[$form->popup('trigger')] }} · {{ \App\Models\Form::POSITIONS[$form->popup('position')] }}.</p>
                    <pre class="code whitespace-pre-wrap">{{ $snippet }}</pre>
                    @if ($form->status !== 'published')<p class="mt-2 text-xs text-amber-700">The form is a draft — the popup won't appear until it is published.</p>@endif
                </div>
            @endif
            <div class="card card-body" x-data="copy(@js($code))">
                <div class="mb-3 flex items-center justify-between"><h2 class="card-title">{{ $form->isPopup() ? 'Embedded version' : 'Embed code' }}</h2>
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

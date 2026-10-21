@props(['target'])
@if (\App\Services\Ai\AiAssistant::isConfigured())
<div class="relative" x-data="Object.assign(aiRewrite(@js(route('ai.rewrite')), @js($target)), { menu: false })">
    <button type="button" class="btn-ghost btn-sm text-brand-700" @click="menu = !menu" :disabled="loading"><x-icon name="bolt" class="size-4" /> <span x-text="loading ? 'Rewriting…' : 'Rewrite with AI'"></span></button>
    <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
        @foreach (\App\Services\Ai\AiAssistant::REWRITES as $key => $label)
            <button type="button" class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="menu = false; rewrite(@js($key))">{{ $label }}</button>
        @endforeach
    </div>
    <p x-show="error" x-cloak class="error absolute right-0 w-64" x-text="error"></p>
</div>
@endif

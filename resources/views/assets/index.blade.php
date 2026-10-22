<x-layouts.app title="Assets">
    <x-page-header title="Assets" subtitle="Images and files for your emails, hosted on your platform.">
        <x-slot:actions>
            <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" x-data>@csrf
                <input type="file" name="files[]" multiple accept="image/*,application/pdf" class="hidden" x-ref="files" @change="$el.form.submit()">
                <button type="button" class="btn-primary" @click="$refs.files.click()"><x-icon name="upload" /> Upload files</button>
            </form>
        </x-slot:actions>
    </x-page-header>
    <x-banner icon="perm_media" title="Your media library" text="Upload logos, product photos and PDFs once and use them in any campaign. Images uploaded in the drag-and-drop builder appear here too."
        :stats="[['image', $assets->where('image', true)->count().' images'], ['description', $assets->where('image', false)->count().' files'], ['storage', number_format($totalSize / 1048576, 1).' MB used']]" />

    @error('files.*')<div class="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</div>@enderror
    <x-pill-tabs :items="[
        ['All', route('assets.index'), ! request('type'), 'apps'],
        ['Images', route('assets.index', ['type' => 'images']), request('type') === 'images', 'image'],
        ['Files', route('assets.index', ['type' => 'files']), request('type') === 'files', 'description'],
    ]" />

    @if ($assets->isEmpty())
        <div class="card"><x-empty title="No assets yet" icon="perm_media">Upload images (JPG, PNG, GIF, WebP) or PDFs up to 10 MB each.</x-empty></div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
            @foreach ($assets as $asset)
                <div class="card group overflow-hidden" x-data="copy(@js($asset['url']))">
                    <a href="{{ $asset['url'] }}" target="_blank" class="flex aspect-[4/3] items-center justify-center bg-[var(--surface-2)]">
                        @if ($asset['image'])
                            <img src="{{ $asset['url'] }}" alt="" loading="lazy" class="size-full object-contain p-2">
                        @else
                            <x-icon name="picture_as_pdf" class="size-12 text-red-500" />
                        @endif
                    </a>
                    <div class="flex items-center justify-between gap-2 border-t border-[var(--line)] px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium" title="{{ $asset['name'] }}">{{ $asset['name'] }}</p>
                            <p class="text-[11px] text-[var(--muted)]">{{ number_format($asset['size'] / 1024) }} KB · {{ \Illuminate\Support\Carbon::createFromTimestamp($asset['modified'])->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0">
                            <button type="button" class="rounded p-1 text-[var(--muted)] hover:text-[var(--text)]" @click="copy()" :title="copied ? 'Copied!' : 'Copy URL'"><x-icon name="link" class="size-4" x-show="!copied" /><x-icon name="check" class="size-4 text-brand-600" x-show="copied" x-cloak /></button>
                            <form method="POST" action="{{ route('assets.destroy', $asset['name']) }}" onsubmit="return confirm('Delete this file? Emails that use it will show a broken image.')">@csrf @method('DELETE')
                                <button class="rounded p-1 text-[var(--muted)] hover:text-red-600" title="Delete"><x-icon name="delete" class="size-4" /></button></form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>

<x-layouts.app title="Platform branding">
    <x-page-header title="Platform branding" subtitle="White-label the app your team logs into. Each client's own logo and colour are set in its Client settings." />
    <form method="POST" action="{{ route('platform.update') }}" enctype="multipart/form-data" class="card card-body max-w-2xl space-y-4">
        @csrf @method('PUT')
        <x-field name="platform_name" label="Product name" :value="$name" :placeholder="config('app.name')" help="Shown in the sidebar, page titles and the login screen." />
        <div>
            <label class="label">Logo</label>
            @if ($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" class="mb-2 h-10 rounded bg-gray-900 object-contain p-1">
                <label class="mb-2 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove logo</label>
            @endif
            <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm">
            <p class="help">Light-coloured logos work best — the sidebar is dark.</p>
            @error('logo')<p class="error">{{ $message }}</p>@enderror
        </div>
        <x-field name="platform_color" type="color" label="Accent colour" :value="$color ?? \App\Services\Branding::DEFAULT_COLOR" class="[&_input]:h-10 [&_input]:p-1" help="Buttons, links and highlights across the app." />
        <div class="flex justify-end"><button class="btn-primary">Save branding</button></div>
    </form>
</x-layouts.app>

<x-layouts.app title="Add website">
    <x-page-header title="Add website" :back="route('websites.index')" />
    <form method="POST" action="{{ route('websites.store') }}" class="mx-auto max-w-3xl space-y-6">
        @csrf
        <div class="card">
            <div class="card-header"><div class="flex items-center gap-2"><x-icon name="language" class="size-5 text-[var(--muted)]" /><h2 class="card-title">Website URL</h2></div><p class="text-xs text-[var(--muted)]">Enter the full URL of the website you want to connect.</p></div>
            <div class="card-body space-y-4">
                <x-field name="url" type="url" label="Website URL" placeholder="https://www.example.com" required autofocus help="Use the full URL including https:// (e.g. https://www.example.com)." />
                <x-field name="name" label="Name" placeholder="Leave blank to use the domain" />
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('websites.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><x-icon name="save" class="size-4.5" /> Add website</button>
        </div>
    </form>
</x-layouts.app>

<x-layouts.app title="Templates">
    <x-page-header title="Email templates" subtitle="Starter layouts plus this client's saved designs.">
        <x-slot:actions><a href="{{ route('templates.create') }}" class="btn-primary"><x-icon name="plus" /> New template</a></x-slot:actions>
    </x-page-header>
    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search templates…" class="input max-w-xs">
        <select name="owner" class="input w-auto" onchange="this.form.submit()">
            <option value="">All templates</option><option value="mine" @selected(request('owner') === 'mine')>My templates</option><option value="system" @selected(request('owner') === 'system')>Starters</option>
        </select>
    </form>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($templates as $template)
            <div class="card overflow-hidden">
                <div class="h-56 overflow-hidden bg-gray-50"><iframe src="{{ route('templates.preview', $template) }}" class="pointer-events-none h-[640px] w-[200%] origin-top-left scale-50 border-0" loading="lazy" sandbox=""></iframe></div>
                <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-4 py-3">
                    <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $template->name }}</span><span class="text-xs text-gray-500">{{ $template->isSystem() ? 'Starter' : 'Updated '.$template->updated_at->diffForHumans() }}</span></span>
                    <span class="flex shrink-0 gap-1">
                        @if ($template->isSystem())
                            <a href="{{ route('templates.create', ['from' => $template->uid]) }}" class="btn-secondary btn-sm">Customise</a>
                        @else
                            <a href="{{ route('templates.builder', $template) }}" class="btn-secondary btn-sm">Design</a>
                            <a href="{{ route('templates.edit', $template) }}" class="btn-ghost btn-sm">HTML</a>
                            <x-confirm-delete :action="route('templates.destroy', $template)" label="" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></x-confirm-delete>
                        @endif
                    </span>
                </div>
            </div>
        @empty
            <div class="card sm:col-span-2 lg:col-span-3 xl:col-span-4"><x-empty title="No templates found" /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
</x-layouts.app>

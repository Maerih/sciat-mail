<x-layouts.app title="Import progress">
    <x-page-header title="Importing contacts" :subtitle="$import->original_name" :back="route('lists.import.create', $list)" />

    <div class="card card-body max-w-2xl" x-data="{ s: @js($import->only(['status', 'total', 'processed', 'created_count', 'updated_count', 'failed_count']) + ['progress' => $import->progress(), 'errors' => $import->errors ?? []]),
        poll() { if (this.s.status === 'done' || this.s.status === 'failed') return; setTimeout(async () => { this.s = await (await fetch(@js(route('lists.import.status', [$list, $import])))).json(); this.poll() }, 1500) } }"
         x-init="poll()">
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-gray-900" x-text="s.status === 'done' ? 'Import finished' : (s.status === 'running' ? 'Importing…' : 'Waiting for a worker…')"></span>
            <span class="tabular-nums text-gray-500" x-text="s.progress + '%'"></span>
        </div>
        <div class="mt-2 h-2.5 rounded-full bg-gray-100"><div class="h-2.5 rounded-full bg-brand-600 transition-all" :style="`width:${s.progress}%`"></div></div>
        <dl class="mt-5 grid grid-cols-3 gap-4 text-center">
            <div><dt class="text-xs text-gray-500 uppercase">New</dt><dd class="text-xl font-semibold" x-text="s.created_count.toLocaleString()"></dd></div>
            <div><dt class="text-xs text-gray-500 uppercase">Updated</dt><dd class="text-xl font-semibold" x-text="s.updated_count.toLocaleString()"></dd></div>
            <div><dt class="text-xs text-gray-500 uppercase">Failed</dt><dd class="text-xl font-semibold text-red-600" x-text="s.failed_count.toLocaleString()"></dd></div>
        </dl>
        <template x-if="s.errors.length">
            <div class="mt-5">
                <h3 class="text-sm font-semibold text-gray-900">Rows that were skipped</h3>
                <ul class="mt-2 max-h-60 overflow-y-auto rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-600"><template x-for="e in s.errors"><li x-text="e"></li></template></ul>
            </div>
        </template>
        <div class="mt-6 flex justify-end" x-show="s.status === 'done'" x-cloak>
            <a href="{{ route('lists.subscribers.index', $list) }}" class="btn-primary">View subscribers</a>
        </div>
    </div>
</x-layouts.app>

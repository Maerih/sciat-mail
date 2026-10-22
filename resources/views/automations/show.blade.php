<x-layouts.app :title="$automation->name">
    <x-page-header :title="$automation->name" :subtitle="$automation->list->name.' · '.$automation->triggerSummary()" :back="route('automations.index')">
        <x-slot:actions>
            <x-status-badge :status="$automation->status" />
            <a href="{{ route('automations.contacts', $automation) }}" class="btn-secondary"><x-icon name="users" /> Contacts</a>
            <a href="{{ route('automations.settings', $automation) }}" class="btn-secondary"><x-icon name="cog" /> Settings</a>
            @if ($automation->isActive())
                <form method="POST" action="{{ route('automations.pause', $automation) }}">@csrf<button class="btn-secondary">Pause</button></form>
            @else
                <form method="POST" action="{{ route('automations.activate', $automation) }}">@csrf<button class="btn-primary" @disabled($problems)><x-icon name="bolt" /> Activate</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Entered" :value="number_format($counts['total'])" icon="login" />
        <x-stat label="In progress" :value="number_format($counts['active'])" icon="pending" />
        <x-stat label="Completed" :value="number_format($counts['completed'])" tone="green" icon="task_alt" />
        <x-stat label="Exited early" :value="number_format($counts['exited'])" hint="Unsubscribed or removed" icon="logout" />
    </div>

    @if ($problems && ! $automation->isActive())
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Before activating:</strong> {{ implode(' ', $problems) }}
        </div>
    @endif

    <div class="card mt-6 overflow-x-auto bg-[radial-gradient(var(--line)_1px,transparent_1px)] [background-size:18px_18px]">
        <div class="flex min-w-max flex-col items-center px-6 py-10">
            <div class="flex w-72 shrink-0 items-center gap-3 rounded-xl border border-brand-500/40 bg-brand-50 p-3.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white"><x-icon name="bolt" class="size-4.5" /></span>
                <div class="min-w-0"><div class="text-xs font-medium tracking-wide text-brand-700 uppercase">Trigger</div><div class="truncate text-sm font-semibold text-gray-900">{{ $automation->triggerSummary() }}</div></div>
            </div>
            @include('automations._add', ['parent' => null, 'branch' => 'next'])
            @if ($flow->isEmpty())
                <p class="text-sm text-gray-500">Click + to add the first step — usually a “Send email”.</p>
            @else
                @include('automations._chain', ['stepId' => $flow->start()])
            @endif
        </div>
    </div>

    @if ($automation->isActive() && in_array($automation->trigger_type, ['subscribed', 'tag_added', 'field_updated']))
        <form method="POST" action="{{ route('automations.enroll', $automation) }}" class="card card-body mt-6 flex flex-wrap items-end gap-3"
              onsubmit="return confirm('Enroll matching existing contacts now?')">
            @csrf
            <div class="min-w-0 flex-1">
                <h2 class="card-title">Add existing contacts</h2>
                <p class="text-sm text-gray-500">The trigger only catches new events. Enroll contacts who are already on the list.</p>
            </div>
            <select name="segment" class="input w-56">
                <option value="">All subscribed contacts</option>
                @foreach ($automation->list->segments as $segment)<option value="{{ $segment->uid }}">{{ $segment->name }}</option>@endforeach
            </select>
            <button class="btn-secondary">Enroll</button>
        </form>
    @endif

    <div class="mt-6 flex justify-end">
        <x-confirm-delete :action="route('automations.destroy', $automation)" label="Delete automation" :message="'Delete '.$automation->name.'? Its emails and statistics are removed too.'" class="btn-ghost text-red-600 hover:bg-red-50" />
    </div>
</x-layouts.app>

<x-layouts.app :title="$subscriber->email">
    <x-page-header :title="$subscriber->email" :subtitle="$list->name" :back="route('lists.subscribers.index', $list)">
        <x-slot:actions><a href="{{ route('lists.subscribers.edit', [$list, $subscriber]) }}" class="btn-secondary">Edit</a></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body space-y-3">
            <div class="flex justify-between text-sm"><span class="text-gray-500">Status</span><x-status-badge :status="$subscriber->status" /></div>
            @foreach ($list->fields as $field)
                <div class="flex justify-between gap-4 text-sm"><span class="text-gray-500">{{ $field->label }}</span><span class="truncate text-right">{{ $subscriber->field($field->tag) ?: '—' }}</span></div>
            @endforeach
            <div class="flex justify-between text-sm"><span class="text-gray-500">Source</span><span>{{ ucfirst($subscriber->source) }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Added</span><span>{{ $subscriber->created_at->format('M j, Y H:i') }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Last open</span><span>{{ $subscriber->last_open_at?->diffForHumans() ?? 'Never' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Last click</span><span>{{ $subscriber->last_click_at?->diffForHumans() ?? 'Never' }}</span></div>
            @if ($subscriber->tags)
                <div class="flex flex-wrap gap-1 pt-1">@foreach ($subscriber->tags as $tag)<span class="badge-purple">{{ $tag }}</span>@endforeach</div>
            @endif
        </div>
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Activity timeline</h2></div>
            <ul class="divide-y divide-gray-100">
                @foreach ($timeline as $item)
                    @php
                        $label = match ($item['type']) {
                            'sent' => 'Email sent', 'open' => 'Opened', 'click' => 'Clicked a link', 'bounce' => ucfirst($item['subtype'] ?? '').' bounce',
                            'complaint' => 'Marked as spam', 'unsubscribe' => 'Unsubscribed', 'added' => 'Added to list ('.$item['subtype'].')', default => ucfirst($item['type']),
                        };
                        $tone = match ($item['type']) { 'open', 'click' => 'bg-emerald-500', 'bounce', 'complaint' => 'bg-red-500', 'unsubscribe' => 'bg-gray-400', default => 'bg-brand-500' };
                    @endphp
                    <li class="flex gap-3 px-5 py-3 text-sm">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $tone }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-gray-900">{{ $label }}</span>
                            @if ($item['campaign'])<span class="text-gray-500"> · <a class="hover:underline" href="{{ route('campaigns.show', $item['campaign']) }}">{{ $item['campaign']->name }}</a></span>@endif
                            @if ($item['url'])<div class="truncate text-xs text-gray-500">{{ $item['url'] }}</div>@endif
                        </div>
                        <span class="shrink-0 text-xs text-gray-400">{{ $item['at']?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.app>

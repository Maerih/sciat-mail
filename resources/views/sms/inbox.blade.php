<x-layouts.app title="SMS inbox">
    <x-page-header title="SMS inbox" subtitle="Replies from your contacts. STOP, ACHA and similar replies opt people out of SMS automatically." />
    <div class="card grid min-h-[560px] overflow-hidden md:grid-cols-[300px_1fr]">
        <div class="border-b border-[var(--line)] md:border-r md:border-b-0">
            @forelse ($threads as $thread)
                @php $lastMessage = $last[$thread->last_id] ?? null; @endphp
                <a href="{{ route('sms.inbox', ['phone' => $thread->phone]) }}" @class(['flex items-start gap-3 border-b border-[var(--line)] px-4 py-3 hover:bg-[var(--surface-2)]', 'bg-[var(--surface-2)]' => $thread->phone === $phone])>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600"><x-icon name="person" class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2"><span class="truncate font-mono text-xs font-semibold">{{ $thread->phone }}</span>
                            @if ($thread->unread)<span class="rounded-full bg-brand-500 px-1.5 text-[10px] font-bold text-[var(--on-brand)]">{{ $thread->unread }}</span>@endif</span>
                        <span class="block truncate text-xs text-[var(--muted)]">{{ $lastMessage?->direction === 'out' ? 'You: ' : '' }}{{ $lastMessage?->body }}</span>
                    </span>
                </a>
            @empty
                <div class="p-6 text-center text-sm text-[var(--muted)]"><x-icon name="forum" class="mx-auto mb-2 size-8" /><p>No replies yet. Set your provider's incoming-messages URL (SMS → Providers) to receive them here.</p></div>
            @endforelse
        </div>
        <div class="flex flex-col">
            @if ($phone)
                <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-3">
                    <div><p class="font-mono text-sm font-semibold">{{ $phone }}</p>
                        @if ($contact)<a href="{{ route('lists.subscribers.show', [$contact->list->uid, $contact->uid]) }}" class="text-xs text-brand-600 hover:underline">{{ $contact->email }} · {{ $contact->list->name }}</a>@else<p class="text-xs text-[var(--muted)]">Not in your lists</p>@endif</div>
                    @if ($contact?->sms_opted_out_at)<span class="badge-red">Opted out of SMS</span>@endif
                </div>
                <div class="flex-1 space-y-2 overflow-y-auto p-5" x-data x-init="$el.scrollTop = $el.scrollHeight" style="max-height: 460px">
                    @foreach ($conversation as $message)
                        <div @class(['flex', 'justify-end' => $message->direction === 'out'])>
                            <div @class(['max-w-[75%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-line', 'rounded-br-sm bg-brand-600 text-[var(--on-brand)]' => $message->direction === 'out', 'rounded-bl-sm bg-[var(--surface-2)]' => $message->direction === 'in'])>
                                {{ $message->body }}
                                <div class="mt-1 text-[10px] opacity-70">{{ $message->created_at->setTimezone(\App\Models\Workspace::current()->timezone)->format('M j, H:i') }}@if ($message->direction === 'out') · {{ $message->status }}@endif</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('sms.inbox.reply') }}" class="flex gap-2 border-t border-[var(--line)] p-3">
                    @csrf <input type="hidden" name="phone" value="{{ $phone }}">
                    <input name="message" class="input flex-1" placeholder="Type a reply…" maxlength="1600" required autocomplete="off" @disabled($contact?->sms_opted_out_at)>
                    <button class="btn-primary" @disabled($contact?->sms_opted_out_at)><x-icon name="send" class="size-4.5" /></button>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-[var(--muted)]">Select a conversation.</div>
            @endif
        </div>
    </div>
</x-layouts.app>

<x-layouts.app title="Team inbox">
    @php
        $base = array_filter(['mailbox' => $mailbox?->uid, 'view' => $view !== 'open' ? $view : null, 'q' => request('q')]);
        $initials = fn ($name, $email) => strtoupper(mb_substr(preg_replace('/[^\pL ]/u', '', $name ?: $email ?: '?'), 0, 1));
    @endphp
    <div x-data="{ compose: @js((bool) old('subject')) }">
    <x-page-header title="Team inbox" :subtitle="$mailboxes->isEmpty() ? 'Connect your company mailboxes to read and reply here.' : $mailboxes->pluck('email')->implode(' · ')">
        <x-slot:actions>
            @if ($mailboxes->isNotEmpty())
                <form method="POST" action="{{ route('inbox.sync') }}">@csrf<button class="btn-secondary" title="Check for new mail now"><x-icon name="sync" class="size-4.5" /> Check mail</button></form>
                <button type="button" class="btn-primary" @click="compose = true"><x-icon name="edit" class="size-4.5" /> Compose</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($mailboxes->isEmpty())
        <div class="card"><x-empty title="No mailboxes connected" icon="inbox">Add info@, sales@ or any address with its IMAP/SMTP details and new mail shows up here within a minute.
            @if (auth()->user()->isAdmin())
                <x-slot:action><a href="{{ route('inbox.mailboxes.index') }}" class="btn-primary"><x-icon name="add" /> Connect a mailbox</a></x-slot:action>
            @endif
        </x-empty></div>
    @else
    <div class="card grid min-h-[640px] overflow-hidden lg:grid-cols-[220px_340px_1fr]">
        {{-- Mailboxes and views --}}
        <aside class="space-y-5 border-b border-[var(--line)] p-3 lg:border-r lg:border-b-0">
            <div>
                <p class="nav-section !pt-1">Mailboxes</p>
                <a href="{{ route('inbox.index', array_filter(['view' => $base['view'] ?? null])) }}" @class(['nav-link', 'active' => ! $mailbox])><x-icon name="all_inbox" class="size-5" /> <span class="flex-1">All inboxes</span>
                    @if ($unread->sum())<span class="text-xs font-semibold">{{ $unread->sum() }}</span>@endif</a>
                @foreach ($mailboxes as $box)
                    <a href="{{ route('inbox.index', array_filter(['mailbox' => $box->uid, 'view' => $base['view'] ?? null])) }}" @class(['nav-link', 'active' => $mailbox?->id === $box->id]) title="{{ $box->email }}">
                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $box->color }}"></span> <span class="flex-1 truncate">{{ $box->name }}</span>
                        @if ($unread[$box->id] ?? 0)<span class="text-xs font-semibold">{{ $unread[$box->id] }}</span>@endif
                        @if ($box->last_error)<x-icon name="error" class="size-4 text-red-500" title="{{ $box->last_error }}" />@endif
                    </a>
                @endforeach
            </div>
            <div>
                <p class="nav-section !pt-1">Views</p>
                @foreach (\App\Http\Controllers\InboxController::VIEWS as $key => $label)
                    <a href="{{ route('inbox.index', array_filter(['mailbox' => $mailbox?->uid, 'view' => $key === 'open' ? null : $key])) }}" @class(['nav-link', 'active' => $view === $key])>
                        <x-icon :name="['open' => 'inbox', 'unread' => 'mark_email_unread', 'starred' => 'star', 'mine' => 'person', 'sent' => 'send', 'done' => 'task_alt'][$key]" class="size-5" /> {{ $label }}</a>
                @endforeach
            </div>
            @if (auth()->user()->isAdmin())<a href="{{ route('inbox.mailboxes.index') }}" class="nav-link"><x-icon name="settings" class="size-5" /> Manage mailboxes</a>@endif
        </aside>

        {{-- Conversation list --}}
        <div class="flex min-h-0 flex-col border-b border-[var(--line)] lg:border-r lg:border-b-0">
            <form class="border-b border-[var(--line)] p-3">
                @foreach (array_filter(['mailbox' => $mailbox?->uid, 'view' => $base['view'] ?? null]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <div class="relative"><x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4.5 text-[var(--muted)]" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search mail…" class="input pl-10"></div>
            </form>
            <div class="flex-1 overflow-y-auto" style="max-height: 720px">
                @forelse ($threads as $t)
                    <a href="{{ route('inbox.index', $base + ['thread' => $t->uid]) }}" @class(['flex gap-3 border-b border-[var(--line)] px-4 py-3 transition hover:bg-[var(--surface-2)]', 'bg-[var(--surface-2)]' => $thread?->id === $t->id])>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" style="background: {{ $t->mailbox->color }}">{{ $initials($t->contact_name, $t->contact_email) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span @class(['truncate text-sm', 'font-bold' => $t->unread, 'font-medium' => ! $t->unread])>{{ $t->contact_name ?: $t->contact_email }}</span>
                                <span class="shrink-0 text-[11px] text-[var(--muted)]">{{ $t->last_message_at?->isToday() ? $t->last_message_at->format('H:i') : $t->last_message_at?->format('M j') }}</span>
                            </span>
                            <span @class(['block truncate text-[13px]', 'font-semibold' => $t->unread])>{{ $t->subject }}</span>
                            <span class="block truncate text-xs text-[var(--muted)]">{{ $t->snippet }}</span>
                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                @if ($t->unread)<span class="size-2 rounded-full bg-brand-500"></span>@endif
                                @if (! $mailbox && $mailboxes->count() > 1)<span class="text-[10px] text-[var(--muted)]">{{ $t->mailbox->name }}</span>@endif
                                @if ($t->starred)<x-icon name="star" fill class="size-3.5 text-amber-500" />@endif
                                @if ($t->message_count > 1)<span class="text-[10px] text-[var(--muted)]">{{ $t->message_count }}</span>@endif
                                @if ($t->assignee)<span class="badge-gray !py-0 !text-[10px]">{{ $t->assignee->name }}</span>@endif
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="p-8 text-center text-sm text-[var(--muted)]"><x-icon name="inbox" class="mx-auto mb-2 size-8" />Nothing here.</div>
                @endforelse
            </div>
            @if ($threads->hasPages())<div class="border-t border-[var(--line)] px-3 py-2 text-xs">{{ $threads->links() }}</div>@endif
        </div>

        {{-- Conversation --}}
        <section class="flex min-w-0 flex-col">
            @if ($thread)
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--line)] px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold break-words">{{ $thread->subject }}</h2>
                        <p class="text-xs text-[var(--muted)]">{{ $thread->contact_name ? $thread->contact_name.' · ' : '' }}{{ $thread->contact_email }} · via {{ $thread->mailbox->email }}
                            @if ($contact) · <a href="{{ route('lists.subscribers.show', [$contact->list->uid, $contact->uid]) }}" class="text-brand-600 hover:underline">subscriber in {{ $contact->list->name }}</a>@endif</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        @foreach ([[$thread->starred ? 'unstar' : 'star', $thread->starred ? 'star' : 'star_outline', $thread->starred ? 'Unstar' : 'Star'], ['unread', 'mark_email_unread', 'Mark unread'], [$thread->status === 'done' ? 'reopen' : 'done', $thread->status === 'done' ? 'undo' : 'task_alt', $thread->status === 'done' ? 'Reopen' : 'Done']] as [$action, $icon, $label])
                            <form method="POST" action="{{ route('inbox.update', [$thread] + $base) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}">
                                <button class="btn-ghost btn-sm" title="{{ $label }}"><x-icon :name="$icon" class="size-4.5" /> <span class="hidden xl:inline">{{ $label }}</span></button></form>
                        @endforeach
                        <form method="POST" action="{{ route('inbox.update', [$thread] + $base) }}" class="flex items-center">@csrf @method('PATCH')<input type="hidden" name="action" value="assign">
                            <select name="user_id" class="input !py-1 text-xs" onchange="this.form.submit()" title="Assign">
                                <option value="">Unassigned</option>
                                @foreach ($team as $member)<option value="{{ $member->id }}" @selected($thread->assigned_user_id === $member->id)>{{ $member->name }}</option>@endforeach
                            </select></form>
                    </div>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto p-5" style="max-height: 560px" x-data x-init="$el.scrollTop = $el.scrollHeight">
                    @foreach ($thread->messages as $message)
                        <article @class(['rounded-xl border p-4', 'border-[var(--line)] bg-[var(--surface)]' => $message->direction === 'in', 'border-brand-500/30 bg-brand-50' => $message->direction === 'out']) x-data="{ open: {{ $loop->last ? 'true' : 'false' }} }">
                            <header class="flex cursor-pointer items-start justify-between gap-3" @click="open = !open">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--surface-2)] text-xs font-semibold">{{ $initials($message->from_name, $message->from_email) }}</span>
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $message->from_name ?: $message->from_email }}
                                        @if ($message->direction === 'out' && $message->user)<span class="text-xs font-normal text-[var(--muted)]">· sent by {{ $message->user->name }}</span>@endif</p>
                                        <p class="truncate text-xs text-[var(--muted)]">to {{ collect($message->to)->pluck('email')->implode(', ') }}@if ($message->cc) · cc {{ collect($message->cc)->pluck('email')->implode(', ') }}@endif</p></div>
                                </div>
                                <span class="shrink-0 text-xs text-[var(--muted)]">{{ $message->sent_at?->format('M j, H:i') }}</span>
                            </header>
                            <p x-show="!open" class="mt-2 truncate text-sm text-[var(--muted)]">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $message->plainText())), 160) }}</p>
                            <div x-show="open" x-cloak class="mt-3">
                                <iframe class="w-full rounded-lg bg-white" sandbox="allow-popups allow-popups-to-escape-sandbox" srcdoc="{{ $message->safeHtml() }}"
                                    onload="this.style.height = Math.min(Math.max(this.contentWindow?.document?.body?.scrollHeight || 200, 120) + 24, 1600) + 'px'" style="height: 240px"></iframe>
                                @if ($message->attachments)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($message->attachments as $i => $file)
                                            <a href="{{ route('inbox.attachment', [$message, $i]) }}" class="flex items-center gap-2 rounded-lg border border-[var(--line)] px-3 py-1.5 text-xs hover:bg-[var(--surface-2)]">
                                                <x-icon name="attach_file" class="size-4" /> {{ $file['name'] }} <span class="text-[var(--muted)]">{{ number_format($file['size'] / 1024) }} KB</span></a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                @php
                    $last = $thread->messages->last();
                    $others = collect($last?->to ?? [])->merge($last?->cc ?? [])->pluck('email')->reject(fn ($e) => $e === $thread->mailbox->email || $e === $thread->contact_email)->unique()->implode(', ');
                @endphp
                <form method="POST" action="{{ route('inbox.reply', $thread) }}" enctype="multipart/form-data" class="space-y-2 border-t border-[var(--line)] p-4" x-data="{ all: false, files: [] }">
                    @csrf
                    <div class="flex flex-wrap items-center gap-2 text-xs text-[var(--muted)]">
                        <span>Reply to <strong class="text-[var(--text)]">{{ $thread->contact_email }}</strong> from {{ $thread->mailbox->email }}</span>
                        @if ($others)<label class="flex items-center gap-1"><input type="checkbox" x-model="all" class="rounded text-brand-600"> Reply all</label>@endif
                    </div>
                    <input type="hidden" name="to" value="{{ $thread->contact_email }}">
                    <input x-show="all" x-cloak name="cc" :disabled="!all" class="input text-sm" value="{{ $others }}" placeholder="Cc">
                    <textarea name="body" rows="4" class="input" placeholder="Write your reply…" required>{{ old('body') }}</textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="btn-ghost btn-sm cursor-pointer"><x-icon name="attach_file" class="size-4.5" /> <span x-text="files.length ? files.length + ' file(s)' : 'Attach'"></span>
                            <input type="file" name="files[]" multiple class="hidden" @change="files = [...$event.target.files]"></label>
                        <button class="btn-primary"><x-icon name="send" class="size-4.5" /> Send</button>
                    </div>
                    @if ($thread->mailbox->signature)<p class="text-[11px] text-[var(--muted)]">Your mailbox signature and the previous message are added automatically.</p>@endif
                </form>
            @else
                <div class="flex flex-1 flex-col items-center justify-center p-10 text-center text-sm text-[var(--muted)]">
                    <x-icon name="forum" class="mb-3 size-10" />
                    <p>Select a conversation to read it.</p>
                    <p class="mt-1 text-xs">New mail is fetched every minute from {{ $mailboxes->count() }} {{ \Illuminate\Support\Str::plural('mailbox', $mailboxes->count()) }}.</p>
                </div>
            @endif
        </section>
    </div>
    @endif

    {{-- Compose --}}
    <div x-show="compose" x-cloak class="overlay fixed inset-0 z-50 flex items-end justify-end p-4 sm:items-center sm:justify-center" @keydown.escape.window="compose = false">
        <form method="POST" action="{{ route('inbox.compose') }}" enctype="multipart/form-data" class="card w-full max-w-2xl" @click.outside="compose = false">
            @csrf
            <div class="card-header"><h2 class="card-title">New message</h2><button type="button" class="btn-ghost btn-sm" @click="compose = false"><x-icon name="close" class="size-4" /></button></div>
            <div class="card-body space-y-3">
                <div><label class="label">From</label><select name="mailbox" class="input">@foreach ($mailboxes as $box)<option value="{{ $box->uid }}" @selected(old('mailbox', $mailbox?->uid) === $box->uid)>{{ $box->name }} &lt;{{ $box->email }}&gt;</option>@endforeach</select></div>
                <x-field name="to" label="To" :value="old('to')" placeholder="name@example.com, another@example.com" required />
                <x-field name="cc" label="Cc (optional)" :value="old('cc')" />
                <x-field name="subject" label="Subject" :value="old('subject')" required />
                <x-field name="body" type="textarea" label="Message" :value="old('body')" rows="8" required />
                <input type="file" name="files[]" multiple class="block text-sm text-[var(--muted)]">
            </div>
            <div class="flex justify-end gap-2 border-t border-[var(--line)] px-6 py-4"><button type="button" class="btn-secondary" @click="compose = false">Cancel</button><button class="btn-primary"><x-icon name="send" class="size-4.5" /> Send</button></div>
        </form>
    </div>
    </div>
</x-layouts.app>

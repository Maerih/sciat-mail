<x-layouts.app title="Mailboxes">
    <x-page-header title="Mailboxes" subtitle="Company addresses shown in the team inbox." :back="route('inbox.index')" />
    <x-banner icon="alternate_email" title="Your mail, inside the platform" text="Connect info@, sales@, support@ or personal addresses. New mail is fetched every minute, replies go out through the mailbox's own server and are saved to its Sent folder — webmail stays in sync."
        :stats="[['inbox', $mailboxes->count().' connected'], ['forum', $mailboxes->sum('open_count').' open conversations']]" />
    <div class="grid items-start gap-6 xl:grid-cols-[1fr_440px]">
        <div class="card">
            @forelse ($mailboxes as $box)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--line)] px-6 py-4 last:border-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="size-3 shrink-0 rounded-full" style="background: {{ $box->color }}"></span>
                        <div class="min-w-0"><p class="font-medium">{{ $box->name }} <span class="text-sm font-normal text-[var(--muted)]">{{ $box->email }}</span>
                            @if ($box->status !== 'active')<span class="badge-gray ml-1">Paused</span>@endif</p>
                            <p @class(['text-xs', 'text-red-600' => $box->last_error, 'text-[var(--muted)]' => ! $box->last_error])>{{ $box->last_error ?: ($box->last_synced_at ? 'Synced '.$box->last_synced_at->diffForHumans() : 'Not synced yet') }}
                                · {{ $box->members->isEmpty() ? 'Whole team' : $box->members->pluck('name')->implode(', ') }}</p></div>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST" action="{{ route('inbox.mailboxes.test', $box) }}">@csrf<button class="btn-ghost btn-sm">Test</button></form>
                        <a href="{{ route('inbox.mailboxes.index', ['edit' => $box->uid]) }}" class="btn-ghost btn-sm">Edit</a>
                        <x-confirm-delete :action="route('inbox.mailboxes.destroy', $box)" message="Remove this mailbox from the team inbox? Mail on the server is not touched." />
                    </div>
                </div>
            @empty
                <x-empty title="No mailboxes yet" icon="alternate_email">Add your first address with the form.</x-empty>
            @endforelse
        </div>

        @php $m = $editing ?? new \App\Models\InboxMailbox(['imap_port' => 993, 'imap_encryption' => 'ssl', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'status' => 'active', 'color' => '#22c55e']); @endphp
        <form method="POST" action="{{ $editing ? route('inbox.mailboxes.update', $editing) : route('inbox.mailboxes.store') }}" class="card"
              x-data="{ email: @js(old('email', $m->email)), cpanel() { const d = (this.email.split('@')[1] || ''); if (!d) return; for (const k of ['imap_host', 'smtp_host']) this.$root.querySelector('[name=' + k + ']').value = 'mail.' + d; this.$root.querySelector('[name=username]').value = this.email } }">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-header"><h2 class="card-title">{{ $editing ? 'Edit '.$editing->email : 'Add a mailbox' }}</h2>@if ($editing)<a href="{{ route('inbox.mailboxes.index') }}" class="text-xs text-brand-600">Cancel</a>@endif</div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="name" label="Name" :value="$m->name" placeholder="Info" required />
                    <div><label class="label">Email address <span class="text-red-500">*</span></label><input name="email" type="email" x-model="email" class="input" required placeholder="info@sciatafrica.com"></div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs"><span class="text-[var(--muted)]">Fill in:</span>
                    <button type="button" class="btn-secondary btn-sm" @click="cpanel()">cPanel / own domain (mail.domain)</button>
                    <button type="button" class="btn-secondary btn-sm" @click="for (const [k, v] of [['imap_host','imap.gmail.com'],['smtp_host','smtp.gmail.com']]) $root.querySelector('[name=' + k + ']').value = v; $root.querySelector('[name=username]').value = email">Google Workspace</button>
                    <button type="button" class="btn-secondary btn-sm" @click="for (const [k, v] of [['imap_host','outlook.office365.com'],['smtp_host','smtp.office365.com']]) $root.querySelector('[name=' + k + ']').value = v; $root.querySelector('[name=smtp_port]').value = 587; $root.querySelector('[name=smtp_encryption]').value = 'tls'; $root.querySelector('[name=username]').value = email">Microsoft 365</button>
                </div>
                <x-field name="from_name" label="Sender name" :value="$m->from_name" placeholder="SCIAT Africa" />
                <div class="grid grid-cols-[1fr_80px_100px] gap-2">
                    <x-field name="imap_host" label="IMAP (incoming) server" :value="$m->imap_host" required />
                    <x-field name="imap_port" type="number" label="Port" :value="$m->imap_port" required />
                    <div><label class="label">Security</label><select name="imap_encryption" class="input">@foreach (['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => 'None'] as $v => $l)<option value="{{ $v }}" @selected($m->imap_encryption === $v)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="grid grid-cols-[1fr_80px_100px] gap-2">
                    <x-field name="smtp_host" label="SMTP (outgoing) server" :value="$m->smtp_host" required />
                    <x-field name="smtp_port" type="number" label="Port" :value="$m->smtp_port" required />
                    <div><label class="label">Security</label><select name="smtp_encryption" class="input">@foreach (['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => 'None'] as $v => $l)<option value="{{ $v }}" @selected($m->smtp_encryption === $v)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="username" label="Username" :value="$m->username" required autocomplete="off" help="Usually the full email address." />
                    <x-field name="password" type="password" label="Password" :required="! $editing" autocomplete="new-password" :placeholder="$editing ? '•••••••• saved — leave blank to keep' : ''" />
                </div>
                <x-field name="signature" type="textarea" label="Signature (HTML allowed)" :value="$m->signature" rows="3" />
                <div>
                    <label class="label">Who can see this mailbox</label>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-lg border border-[var(--line)] p-2">
                        @foreach ($team as $member)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="members[]" value="{{ $member->id }}" class="rounded text-brand-600" @checked($m->exists && $m->members->contains($member))> {{ $member->name }} <span class="text-xs text-[var(--muted)]">{{ $member->email }}</span></label>@endforeach
                    </div>
                    <p class="help">Leave all unticked for the whole team. Administrators always see every mailbox.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Colour</label><input type="color" name="color" value="{{ $m->color }}" class="input h-10 p-1"></div>
                    <div><label class="label">Status</label><select name="status" class="input"><option value="active" @selected($m->status === 'active')>Active</option><option value="inactive" @selected($m->status === 'inactive')>Paused</option></select></div>
                </div>
            </div>
            <div class="flex justify-end border-t border-[var(--line)] px-6 py-4"><button class="btn-primary"><x-icon name="save" class="size-4.5" /> {{ $editing ? 'Save mailbox' : 'Add mailbox' }}</button></div>
        </form>
    </div>
</x-layouts.app>

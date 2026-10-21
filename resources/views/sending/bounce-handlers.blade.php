<x-layouts.app title="Bounce handlers">
    <x-page-header title="Bounce handlers" subtitle="For SMTP servers: bounce and spam-complaint reports are read from these mailboxes every 5 minutes. Amazon SES, SendGrid and Mailgun report through webhooks instead." />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Mailbox</th><th>Last check</th><th>Processed</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($handlers as $handler)
                            <tr>
                                <td><div class="font-medium text-gray-900">{{ $handler->name }}</div><div class="text-xs text-gray-500">{{ $handler->username }} @ {{ $handler->host }}:{{ $handler->port }} · {{ $handler->folder }}</div>
                                    @if ($handler->last_error)<div class="mt-1 max-w-md truncate text-xs text-red-600" title="{{ $handler->last_error }}">{{ $handler->last_error }}</div>@endif</td>
                                <td class="text-gray-500">{{ $handler->last_checked_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="tabular-nums">{{ number_format($handler->processed_count) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('sending.bounce-handlers.check', $handler) }}" class="inline">@csrf<button class="btn-secondary btn-sm">Check now</button></form>
                                    <x-confirm-delete :action="route('sending.bounce-handlers.destroy', $handler)" label="Remove" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-gray-500">No bounce mailbox yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card card-body text-sm text-gray-600">
                <h2 class="card-title mb-2">Setup</h2>
                <ol class="list-inside list-decimal space-y-1">
                    <li>Create a dedicated mailbox, e.g. <code>bounces@{{ $currentWorkspace->contact_email ? \Illuminate\Support\Str::after($currentWorkspace->contact_email, '@') : 'yourdomain.com' }}</code>.</li>
                    @php $serverNames = $servers->pluck('name')->implode(', '); @endphp
                    <li>Set it as the <strong>Bounce address</strong> on each SMTP sending server{{ $serverNames ? ' ('.$serverNames.')' : '' }}.</li>
                    <li>Add the mailbox here. Ask mailbox providers' feedback loops (e.g. Yahoo, Microsoft JMRP) to send complaints to the same address.</li>
                </ol>
                <p class="help mt-2">Hard bounces and complaints are blacklisted automatically; other mail in the mailbox is left unread.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('sending.bounce-handlers.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="card-title">Add a mailbox</h2>
            <x-field name="name" label="Name" placeholder="Bounce mailbox" required />
            <x-field name="host" label="IMAP host" placeholder="imap.example.com" required />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="port" type="number" label="Port" value="993" required />
                <div><label class="label">Encryption</label><select name="encryption" class="input"><option value="ssl">SSL</option><option value="tls">STARTTLS</option><option value="none">None</option></select></div>
            </div>
            <x-field name="username" label="Username" required autocomplete="off" />
            <x-field name="password" type="password" label="Password" required autocomplete="new-password" />
            <x-field name="folder" label="Folder" value="INBOX" />
            <x-toggle name="delete_processed" label="Delete reports after processing" help="Otherwise they are marked as read." />
            <button class="btn-primary w-full">Add mailbox</button>
        </form>
    </div>
</x-layouts.app>

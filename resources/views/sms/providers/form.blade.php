<x-layouts.app :title="$provider->exists ? $provider->name : 'Add '.$type['label']">
    <x-page-header :title="$provider->exists ? $provider->name : 'Add '.$type['label']" :subtitle="$type['description']" :back="route('sms.providers.index')" />
    @php $locked = $provider->exists && $provider->isShared() && ! auth()->user()->isAdmin(); @endphp
    <div class="grid items-start gap-6 lg:grid-cols-[1fr_380px]">
        <form method="POST" action="{{ $provider->exists ? route('sms.providers.update', $provider) : route('sms.providers.store') }}" class="space-y-6"
              x-data="{ preset(p) { for (const [k, v] of Object.entries(p)) { const el = this.$root.querySelector('[name=\'config[' + k + ']\']'); if (el) { el.value = v } } } }">
            @csrf
            @if ($provider->exists) @method('PUT') @else <input type="hidden" name="type" value="{{ $provider->type }}"> @endif
            <fieldset @disabled($locked) class="card card-body space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Name" :value="$provider->name ?: $type['label']" required />
                    <div><label class="label">Status</label><select name="status" class="input"><option value="active" @selected($provider->status === 'active')>Active</option><option value="inactive" @selected($provider->status === 'inactive')>Inactive</option></select></div>
                </div>
                @if ($provider->type === 'http')
                    <div class="flex flex-wrap items-center gap-2 text-sm"><span class="text-[var(--muted)]">Start from a preset:</span>
                        @foreach (\App\Sms\SmsManager::HTTP_PRESETS as $preset)
                            <button type="button" class="btn-secondary btn-sm" @click="preset(@js(collect($preset)->except('label')->all()))">{{ $preset['label'] }}</button>
                        @endforeach
                    </div>
                @endif
                @foreach ($type['fields'] as $key => $field)
                    @php $secret = ! empty($field['secret']) && $provider->exists; @endphp
                    @if (! empty($field['options']))
                        <div><label class="label">{{ $field['label'] }}</label>
                            <select name="config[{{ $key }}]" class="input">@foreach ($field['options'] as $value => $label)<option value="{{ $value }}" @selected($provider->configValue($key) === $value)>{{ $label }}</option>@endforeach</select></div>
                    @elseif (! empty($field['textarea']))
                        <x-field :name="'config['.$key.']'" type="textarea" :label="$field['label']" :value="$secret ? '' : $provider->configValue($key)" :help="($field['help'] ?? '').($secret ? ' Saved — leave blank to keep.' : '')" class="font-mono" rows="5" />
                    @else
                        <x-field :name="'config['.$key.']'" :type="! empty($field['secret']) ? 'password' : 'text'" :label="$field['label']" :value="$secret ? '' : $provider->configValue($key)"
                            :placeholder="$secret ? '•••••••• saved — leave blank to keep' : ''" :help="$field['help'] ?? null" :required="str_contains($field['rules'], 'required') && ! $secret" autocomplete="off" />
                    @endif
                @endforeach
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="sender_id" label="Default sender ID" :value="$provider->sender_id" help="Registered alphanumeric sender (e.g. SCIAT) or short code. Kenyan networks require sender IDs to be registered with your provider." />
                    <x-field name="per_second" type="number" label="Messages per second" :value="$provider->per_second" min="1" max="500" help="Stay within your account's throughput limit." />
                </div>
                @if (! $provider->exists && auth()->user()->isAdmin())
                    <x-toggle name="shared" label="Share with all clients" help="Every client can send through this provider. Only administrators can change it." />
                @endif
            </fieldset>
            @unless ($locked)
                <div class="flex justify-between">
                    @if ($provider->exists)<x-confirm-delete :action="route('sms.providers.destroy', $provider)" label="Delete provider" />@else<span></span>@endif
                    <button class="btn-primary"><x-icon name="save" class="size-4.5" /> Save provider</button>
                </div>
            @endunless
        </form>

        <div class="space-y-6">
            @if ($provider->exists)
                <form method="POST" action="{{ route('sms.providers.test', $provider) }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="card-title">Send a test SMS</h2>
                    <x-field name="to" label="Phone number" :value="old('to')" placeholder="0712 345 678 or +254712345678" required />
                    <x-field name="message" label="Message (optional)" />
                    <button class="btn-secondary w-full"><x-icon name="send" class="size-4.5" /> Send test</button>
                    @if ($provider->last_error)<p class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">Last error: {{ $provider->last_error }}</p>@endif
                </form>
                @if ($type['webhooks'])
                    <div class="card card-body space-y-3">
                        <h2 class="card-title">Delivery reports &amp; replies</h2>
                        <p class="text-xs text-[var(--muted)]">{{ $type['webhooks'] }}</p>
                        @foreach (['status' => 'Delivery report URL', 'inbound' => 'Incoming messages URL'] as $kind => $label)
                            <div x-data="copy(@js($provider->webhookUrl($kind)))"><span class="label">{{ $label }}</span>
                                <div class="flex gap-2"><div class="code flex-1 !text-[11px]">{{ $provider->webhookUrl($kind) }}</div><button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied' : 'Copy'"></button></div></div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="card card-body text-sm text-[var(--muted)]">Save the provider to send a test message and get your delivery-report and incoming-message URLs.</div>
            @endif
        </div>
    </div>
</x-layouts.app>

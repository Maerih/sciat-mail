<x-layouts.app :title="$server->exists ? $server->name : 'Add '.$type['label']">
    <x-page-header :title="$server->exists ? $server->name : 'Add '.$type['label']" :subtitle="$type['description']" :back="route('sending.servers.index')" />
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $server->exists ? route('sending.servers.update', $server) : route('sending.servers.store') }}" class="space-y-6 lg:col-span-2">
            @csrf
            @if ($server->exists) @method('PUT') @else <input type="hidden" name="type" value="{{ $server->type }}"> @endif
            <div class="card card-body space-y-4">
                <h2 class="card-title">General</h2>
                <x-field name="name" label="Name" :value="$server->name ?: $type['label']" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Status</label>
                        <select name="status" class="input"><option value="active" @selected($server->status === 'active')>Active</option><option value="inactive" @selected($server->status === 'inactive')>Inactive</option></select>
                    </div>
                    <x-field name="default_from_email" type="email" label="Default test sender" :value="$server->default_from_email" help="Must be allowed by the provider." />
 @if ($server->usesSmtp() || ! $server->exists)
                    <x-field name="bounce_address" type="email" label="Bounce address (Return-Path)" :value="$server->bounce_address" help="SMTP only: bounces are sent here. Add this mailbox under Sending → Bounce handlers." />
                    @endif
                    <x-field name="hourly_limit" type="number" min="1" label="Hourly limit" :value="$server->hourly_limit" help="Leave empty for no limit." />
                    <x-field name="daily_limit" type="number" min="1" label="Daily limit" :value="$server->daily_limit" help="Respect your provider's quota (e.g. SES sandbox: 200/day)." />
                </div>
                <div class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2" x-data="{ strategy: @js((string) old('warmup_strategy_id', $server->warmup_strategy_id)) }">
                    <div>
                        <label class="label">IP warmup</label>
                        <select name="warmup_strategy_id" class="input" x-model="strategy">
                            <option value="">No warmup</option>
                            @foreach ($strategies as $strategy)<option value="{{ $strategy->id }}">{{ $strategy->name }}</option>@endforeach
                        </select>
                        <p class="help">New IPs/domains: ramp volume up gradually. <a href="{{ route('sending.warmup.index') }}" class="text-brand-600 hover:underline">Manage strategies</a></p>
                    </div>
                    <div x-show="strategy" x-cloak>
                        <x-field name="warmup_started_on" type="date" label="Warmup started on" :value="$server->warmup_started_on?->toDateString()" help="Leave empty to start today." />
                    </div>
                    @if ($server->isWarmingUp())
                        <p class="text-sm text-amber-700 sm:col-span-2">Warming up: day {{ $server->warmupDay() }} of {{ $server->warmupStrategy->days }} · today's quota {{ number_format($server->effectiveDailyLimit()) }} emails.</p>
                    @endif
                </div>
                @if (! $server->exists && auth()->user()->isAdmin())
                    <x-toggle name="shared" label="Share with all clients" help="Otherwise only {{ $currentWorkspace->name }} can use this server." />
                @endif
            </div>
            <div class="card card-body space-y-4">
                <h2 class="card-title">Connection</h2>
                @foreach ($type['fields'] as $key => $field)
                    @if (isset($field['options']))
                        <div>
                            <label class="label">{{ $field['label'] }}</label>
                            <select name="config[{{ $key }}]" class="input">
                                @foreach ($field['options'] as $value => $label)<option value="{{ $value }}" @selected(old('config.'.$key, $server->configValue($key, $field['default'] ?? null)) == $value)>{{ $label }}</option>@endforeach
                            </select>
                            @error('config.'.$key)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <x-field :name="'config['.$key.']'" :label="$field['label']" :type="! empty($field['secret']) ? 'password' : 'text'"
                            :value="! empty($field['secret']) ? '' : $server->configValue($key, $field['default'] ?? null)"
                            :placeholder="! empty($field['secret']) && $server->exists ? '•••••••• (unchanged)' : ''" autocomplete="off" />
                    @endif
                @endforeach
            </div>
            <div class="flex items-center justify-between">
                @if ($server->exists && (! $server->isShared() || auth()->user()->isAdmin()))
                    <x-confirm-delete :action="route('sending.servers.destroy', $server)" label="Delete server" class="btn-ghost text-red-600 hover:bg-red-50" />
                @else
                    <span></span>
                @endif
                <button class="btn-primary">{{ $server->exists ? 'Save' : 'Add server' }}</button>
            </div>
        </form>

        @if ($server->exists)
            <div class="space-y-6">
                <form method="POST" action="{{ route('sending.servers.test', $server) }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="card-title">Send a test email</h2>
                    <x-field name="from" type="email" label="From" :value="$server->default_from_email ?: $currentWorkspace->contact_email" required />
                    <x-field name="to" type="email" label="To" :value="auth()->user()->email" required />
                    <button class="btn-secondary w-full"><x-icon name="paper-airplane" /> Send test</button>
                    @if ($server->last_error)<p class="text-xs break-words text-red-600">Last error: {{ $server->last_error }}</p>@endif
                </form>
                @if ($server->webhookUrl())
                    <div class="card card-body space-y-2" x-data="copy(@js($server->webhookUrl()))">
                        <h2 class="card-title">Bounce & complaint webhook</h2>
                        <p class="text-sm text-gray-600">
                            @switch($server->webhookProvider())
                                @case('ses') Create an SNS topic (HTTPS subscription to this URL) and set it as the Bounce and Complaint notification target for your SES identity or configuration set. The subscription is confirmed automatically. @break
                                @case('sendgrid') In SendGrid → Settings → Mail Settings → Event Webhook, paste this URL and enable Bounced, Dropped, Spam Reports and Unsubscribes. @break
                                @case('mailgun') In Mailgun → Sending → Webhooks, add this URL for Permanent Failure, Temporary Failure, Spam Complaints and Unsubscribes. Add the webhook signing key above to verify requests. @break
                            @endswitch
                        </p>
                        <div class="code">{{ $server->webhookUrl() }}</div>
                        <button type="button" class="btn-secondary btn-sm" @click="copy()" x-text="copied ? 'Copied!' : 'Copy URL'"></button>
                        <p class="help">Must be reachable from the internet — set APP_URL to the public address in production.</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>

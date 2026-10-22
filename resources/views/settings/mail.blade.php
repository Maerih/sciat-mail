<x-layouts.app title="System mail">
    <x-settings-frame :tab="$tab">
        <form method="POST" action="{{ route('settings.mail') }}" class="space-y-6" x-data="{ driver: @js(old('mail_driver', $s('mail_driver', 'smtp'))) }">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Mail driver</h2><p class="text-xs text-[var(--muted)]">How the platform sends its own emails — password resets and team notifications. Campaigns use your sending servers instead.</p></div></div>
                <div class="card-body space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Services\SystemMailConfig::DRIVERS as $value => $label)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border p-3 text-sm" :class="driver === @js($value) ? 'border-brand-500 bg-brand-50' : 'border-[var(--line)]'">
                                <input type="radio" name="mail_driver" value="{{ $value }}" x-model="driver" class="text-brand-600"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <fieldset x-show="driver === 'smtp'" :disabled="driver !== 'smtp'" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <x-field name="mail_host" label="SMTP host" :value="$s('mail_host')" placeholder="smtp.example.com" />
                        <x-field name="mail_port" type="number" label="Port" :value="$s('mail_port', '587')" help="25, 465 (SSL) or 587 (TLS)" />
                        <div><label class="label">Encryption</label><select name="mail_encryption" class="input"><option value="tls" @selected($s('mail_encryption', 'tls') === 'tls')>TLS</option><option value="ssl" @selected($s('mail_encryption') === 'ssl')>SSL</option><option value="" @selected($s('mail_encryption') === '')>None</option></select></div>
                        <x-field name="mail_username" label="Username" :value="$s('mail_username')" autocomplete="off" />
                    </fieldset>
                    <fieldset x-show="driver === 'ses'" :disabled="driver !== 'ses'" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <x-field name="mail_username" label="AWS access key ID" :value="$s('mail_username')" autocomplete="off" />
                        <div><label class="label">AWS region</label><select name="mail_region" class="input">@foreach (\App\Models\SendingServer::AWS_REGIONS as $code => $name)<option value="{{ $code }}" @selected($s('mail_region', 'us-east-1') === $code)>{{ $name }} (\{{ $code }})</option>@endforeach</select></div>
                    </fieldset>
                    <div x-show="['smtp', 'ses', 'sendgrid'].includes(driver)" x-cloak>
                        <label for="f-mail_password" class="label" x-text="driver === 'ses' ? 'AWS secret access key' : (driver === 'sendgrid' ? 'SendGrid API key' : 'SMTP password')">Password</label>
                        <input id="f-mail_password" type="password" name="mail_password" class="input" autocomplete="new-password" placeholder="{{ $hasSecret ? '•••••••• saved — leave blank to keep' : '' }}">
                        @error('mail_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <fieldset x-show="driver === 'sendmail'" :disabled="driver !== 'sendmail'" x-cloak><x-field name="mail_sendmail_path" label="Sendmail path" :value="$s('mail_sendmail_path', '/usr/sbin/sendmail -bs -i')" /></fieldset>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Default sender</h2><p class="text-xs text-[var(--muted)]">The from address and name for system emails.</p></div></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-field name="mail_from_address" type="email" label="From email" :value="$s('mail_from_address', config('mail.from.address'))" required />
                    <x-field name="mail_from_name" label="From name" :value="$s('mail_from_name', \App\Services\Branding::appName())" />
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button class="btn-primary"><x-icon name="check" class="size-4.5" /> Save settings</button>
            </div>
        </form>
        <form method="POST" action="{{ route('settings.mail.test') }}" class="card card-body flex items-center justify-between gap-4">
            @csrf
            <div><h2 class="card-title">Send a test</h2><p class="text-xs text-[var(--muted)]">Sends a short email to {{ auth()->user()->email }} with the saved settings.</p></div>
            <button class="btn-secondary"><x-icon name="send" class="size-4.5" /> Send test email</button>
        </form>
    </x-settings-frame>
</x-layouts.app>

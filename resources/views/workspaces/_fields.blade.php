<div class="grid gap-4 sm:grid-cols-2">
    <x-field name="name" label="Client name" :value="$workspace->name" required class="sm:col-span-2" />
    <div>
        <label class="label" for="f-timezone">Timezone</label>
        <select id="f-timezone" name="timezone" class="input">
            @foreach (timezone_identifiers_list() as $tz)
                <option value="{{ $tz }}" @selected(old('timezone', $workspace->timezone ?? 'UTC') === $tz)>{{ $tz }}</option>
            @endforeach
        </select>
        <p class="help">Used for scheduling and sending windows.</p>
    </div>
    <x-field name="contact_email" type="email" label="Contact email" :value="$workspace->contact_email" help="Default sender and notification address." />
    <x-field name="company" label="Company name" :value="$workspace->company" />
    <x-field name="address" label="Postal address" :value="$workspace->address" help="Shown in email footers (required by CAN-SPAM)." />
    <div>
        <label class="label" for="f-sms_country">SMS country</label>
        <select id="f-sms_country" name="sms_country" class="input">
            <option value="">Platform default ({{ \App\Sms\PhoneNumber::defaultCountry() }})</option>
            @foreach (config('calling_codes') as $code => [$dial])<option value="{{ $code }}" @selected(old('sms_country', $workspace->sms_country) === $code)>{{ config('countries.'.$code, $code) }} (+{{ $dial }})</option>@endforeach
        </select>
        <p class="help">Local numbers like 0712 345 678 are read as this country's.</p>
    </div>
</div>

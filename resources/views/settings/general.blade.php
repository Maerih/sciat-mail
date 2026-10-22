<x-layouts.app title="General settings">
    <x-settings-frame :tab="$tab">
        <form method="POST" action="{{ route('settings.general') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Site information</h2><p class="text-xs text-[var(--muted)]">Name and description used in the app, emails and browser tabs.</p></div></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <span class="label">Site URL</span>
                        <div class="code">{{ config('app.url') }}</div>
                        <p class="help">Set by APP_URL in .env. Used for tracking links, webhooks and signed URLs — must be the public address in production.</p>
                    </div>
                    <x-field name="platform_name" label="Site name" :value="$s('platform_name')" :placeholder="config('app.name')" />
                    <x-field name="platform_description" label="Site description" :value="$s('platform_description')" placeholder="Email marketing for our clients" />
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Branding & appearance</h2><p class="text-xs text-[var(--muted)]">Logo, favicon, accent colour and default theme.</p></div></div>
                <div class="card-body grid gap-5 sm:grid-cols-2">
                    @foreach (['logo' => ['platform_logo', 'Logo', 'Shown in the sidebar and on the login page. SVG or PNG; light logos suit the dark theme.'], 'favicon' => ['platform_favicon', 'Favicon', 'Browser tab icon. 32×32 PNG, ICO or SVG.']] as $field => [$key, $label, $help])
                        <div>
                            <span class="label">{{ $label }}</span>
                            <div class="flex items-center gap-3 rounded-lg border border-dashed border-[var(--line)] p-3">
                                <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[var(--surface-2)]">
                                    @if ($s($key))<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s($key)) }}" alt="" class="max-h-10 max-w-10 object-contain">@else<x-icon name="image" class="size-6 text-[var(--muted)]" />@endif
                                </span>
                                <input type="file" name="{{ $field }}" class="block w-full text-xs text-[var(--muted)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--surface-2)] file:px-3 file:py-1.5 file:text-xs file:text-[var(--text)]">
                            </div>
                            <p class="help">{{ $help }}</p>
                            @if ($s($key))<label class="mt-1 flex items-center gap-2 text-xs text-[var(--muted)]"><input type="checkbox" name="remove_{{ $field }}" value="1" class="rounded border-[var(--line)]"> Remove</label>@endif
                            @error($field)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <x-field name="platform_color" type="color" label="Accent colour" :value="$s('platform_color', '#4ade80')" class="[&_input]:h-10 [&_input]:p-1" help="Buttons, links and highlights." />
                    <div>
                        <label class="label">Default theme</label>
                        <select name="default_theme" class="input">
                            <option value="dark" @selected($s('default_theme', 'dark') === 'dark')>Dark</option>
                            <option value="light" @selected($s('default_theme') === 'light')>Light</option>
                        </select>
                        <p class="help">Each person can still switch with the moon/sun button.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Site status</h2><p class="text-xs text-[var(--muted)]">Take the app offline for maintenance. Administrators can still log in.</p></div></div>
                <div class="card-body space-y-4">
                    <x-toggle name="site_online" label="Site online" help="When off, team members and client portal users see the message below." :checked="$s('site_online', '1') === '1'" />
                    <x-field name="offline_message" type="textarea" rows="2" label="Offline message" :value="$s('offline_message', 'We are performing scheduled maintenance. Please check back soon.')" />
                </div>
            </div>
            <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" class="size-4.5" /> Save settings</button></div>
        </form>
    </x-settings-frame>
</x-layouts.app>

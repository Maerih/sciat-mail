<x-install-layout title="Administrator" step="admin">
    @if ($alreadyInstalled)
        <div class="card card-body text-sm">An administrator already exists in this database, so the platform looks installed. Set <code>APP_INSTALLER=false</code> in your <code>.env</code> file and <a href="/" class="text-brand-600 underline">open the site</a>.</div>
    @else
        <form method="POST" action="{{ route('install.admin.save') }}" class="card">
            @csrf
            <div class="card-header"><div><h2 class="card-title">Platform and administrator</h2><p class="text-xs text-[var(--muted)]">Tables are ready. Name the platform and create your administrator account.</p></div></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field name="app_name" label="Platform name" :value="old('app_name', 'SCIAT Mail')" required />
                <x-field name="app_url" type="url" label="Website address" :value="old('app_url', request()->root())" required help="The address people use to open the platform." />
                <x-field name="name" label="Your name" :value="old('name')" required />
                <x-field name="email" type="email" label="Your email" :value="old('email')" required />
                <x-field name="password" type="password" label="Password" required autocomplete="new-password" help="At least 10 characters." />
                <x-field name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
            </div>
            <div class="flex justify-end border-t border-[var(--line)] px-6 py-4">
                <button class="btn-primary">Finish installation <x-icon name="arrow_forward" class="size-4.5" /></button>
            </div>
        </form>
    @endif
</x-install-layout>

<x-layouts.app title="Profile">
    <x-page-header title="Your profile" />
    <form method="POST" action="{{ route('profile.update') }}" class="card card-body max-w-xl space-y-4">
        @csrf @method('PUT')
        <x-field name="name" label="Name" :value="$user->name" required />
        <x-field name="email" type="email" label="Email" :value="$user->email" required />
        <div class="border-t border-gray-100 pt-4 text-sm font-semibold text-gray-900">Change password</div>
        <x-field name="current_password" type="password" label="Current password" autocomplete="current-password" />
        <x-field name="password" type="password" label="New password" autocomplete="new-password" />
        <x-field name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" />
        <div class="flex justify-end"><button class="btn-primary">Save</button></div>
    </form>
</x-layouts.app>

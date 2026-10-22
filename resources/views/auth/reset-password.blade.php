<x-layouts.guest title="Choose a new password">
    <div class="card p-8">
        <h1 class="text-center text-[22px] font-bold">Choose a new password</h1>
        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-field name="email" type="email" label="Email" :value="$email" required />
            <x-field name="password" type="password" label="New password" autocomplete="new-password" required help="At least 10 characters." />
            <x-field name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
            <button class="btn-primary w-full py-2.5">Save password</button>
        </form>
    </div>
</x-layouts.guest>

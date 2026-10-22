<x-layouts.guest title="Forgot password">
    <div class="card p-8">
        <h1 class="text-center text-[22px] font-bold">Reset your password</h1>
        <p class="mt-1.5 text-center text-sm text-[var(--muted)]">Enter your email and we'll send you a reset link.</p>
        @include('partials.flash')
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <x-field name="email" type="email" label="Email" autofocus required />
            <button class="btn-primary w-full py-2.5">Send reset link</button>
        </form>
        <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="text-brand-600 hover:underline">Back to log in</a></p>
    </div>
</x-layouts.guest>

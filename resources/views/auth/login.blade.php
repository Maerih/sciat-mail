<x-layouts.guest title="Log in">
    <div class="mb-8 flex flex-col items-center gap-3">
        @if ($platformLogo = \App\Services\Branding::appLogo())
            <img src="{{ $platformLogo }}" alt="" class="h-11 max-w-[14rem] object-contain">
        @else
            <span class="flex items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-[var(--on-brand)]"><x-icon name="send" class="size-5.5" /></span>
                <span class="text-2xl font-bold tracking-tight">{{ \App\Services\Branding::appName() }}</span>
            </span>
        @endif
    </div>
    <div class="card p-8">
        <h1 class="text-center text-[22px] font-bold">Log in to your account</h1>
        <p class="mt-1.5 text-center text-sm text-[var(--muted)]">Welcome back. Enter your details to continue.</p>
        @if (session('success'))<div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-center text-sm text-emerald-700">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
            @csrf
            <x-field name="email" type="email" label="Username or Email" autofocus autocomplete="username" required />
            <div x-data="{ show: false }">
                <label for="f-password" class="label">Password</label>
                <div class="relative">
                    <input id="f-password" :type="show ? 'text' : 'password'" name="password" class="input pr-10" autocomplete="current-password" required>
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-[var(--muted)] hover:text-[var(--text)]" aria-label="Show password">
                        <x-icon name="visibility" class="size-5" x-show="!show" /><x-icon name="visibility_off" class="size-5" x-show="show" x-cloak />
                    </button>
                </div>
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-[var(--muted)]">
                    <input type="checkbox" name="remember" value="1" class="rounded border-[var(--line)] bg-[var(--surface-2)] text-brand-600 focus:ring-brand-500"> Remember me
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-brand-600 hover:underline">Forgot password?</a>
            </div>
            <button class="btn-primary w-full py-2.5"><x-icon name="lock" class="size-4.5" /> Log in</button>
        </form>
    </div>
    <p class="mt-6 text-center text-xs text-[var(--muted)]">&copy; {{ date('Y') }} {{ \App\Services\Branding::appName() }}. All rights reserved.</p>
</x-layouts.guest>

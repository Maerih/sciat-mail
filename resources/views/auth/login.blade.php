<x-layouts.guest title="Log in">
    <div class="mb-8 flex items-center justify-center gap-2">
        <div class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-white"><x-icon name="paper-airplane" class="size-5" /></div>
        <span class="text-lg font-semibold">{{ config('app.name') }}</span>
    </div>
    <div class="card card-body p-7">
        <h1 class="text-lg font-semibold text-gray-900">Log in to your account</h1>
        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <x-field name="email" type="email" label="Email" autofocus autocomplete="username" required />
            <x-field name="password" type="password" label="Password" autocomplete="current-password" required />
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-600"> Remember me
            </label>
            <button class="btn-primary w-full">Log in</button>
        </form>
    </div>
</x-layouts.guest>

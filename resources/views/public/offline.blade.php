<x-layouts.guest title="Offline">
    <div class="card p-8 text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-600"><x-icon name="construction" class="size-7" /></span>
        <h1 class="mt-4 text-xl font-bold">We'll be right back</h1>
        <p class="mt-2 text-sm text-[var(--muted)]">{{ $message }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">@csrf<button class="btn-secondary">Log out</button></form>
    </div>
</x-layouts.guest>

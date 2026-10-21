<x-layouts.guest title="Unsubscribe">
    <div class="card card-body p-8 text-center">
        @if ($done)
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-600"><x-icon name="check" class="size-6" /></div>
            <h1 class="mt-4 text-lg font-semibold text-gray-900">You have been unsubscribed</h1>
            <p class="mt-2 text-sm text-gray-600"><strong>{{ $email }}</strong> will no longer receive emails{{ $listName ? ' from '.$listName : '' }}.</p>
        @else
            <h1 class="text-lg font-semibold text-gray-900">Unsubscribe?</h1>
            <p class="mt-2 text-sm text-gray-600">Stop sending emails{{ $listName ? ' from '.$listName : '' }} to <strong>{{ $email }}</strong>.</p>
            <form method="POST" class="mt-6">
                @csrf
                <button class="btn-primary w-full">Unsubscribe me</button>
            </form>
        @endif
    </div>
</x-layouts.guest>

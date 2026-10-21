<x-layouts.app title="New campaign">
    <x-page-header title="Choose a campaign type" :back="route('campaigns.index')" />
    <div class="grid max-w-4xl gap-5 md:grid-cols-2">
        @foreach ([
            ['regular', 'Regular campaign', 'Branded HTML email from a template or your own code, with images, buttons and merge tags.', 'Newsletters · Promotions · Launches'],
            ['plain', 'Plain text', 'A personal, text-only message. Often lands better for outreach and one-to-one style emails.', 'Outreach · Announcements · Personal notes'],
        ] as [$type, $title, $description, $uses])
            <form method="POST" action="{{ route('campaigns.store') }}" class="card card-body flex flex-col">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                <p class="mt-2 flex-1 text-sm text-gray-600">{{ $description }}</p>
                <p class="mt-3 text-xs font-medium text-gray-500">{{ $uses }}</p>
                <input name="name" placeholder="Campaign name (optional)" class="input mt-4">
                <button class="btn-primary mt-3">Create</button>
            </form>
        @endforeach
    </div>
    <p class="mt-6 max-w-4xl text-sm text-gray-500">A/B tests and automations are planned for the next phase.</p>
</x-layouts.app>

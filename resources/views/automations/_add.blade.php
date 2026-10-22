{{-- "+" connector that inserts a step at this point of the flow --}}
<div class="flex flex-col items-center" x-data="{ open: false }">
    <div class="h-5 w-px bg-gray-300"></div>
    <div class="relative">
        <button type="button" @click="open = !open" class="flex size-7 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500 shadow-xs hover:border-brand-500 hover:text-brand-600" title="Add a step">
            <x-icon name="plus" class="size-4" />
        </button>
        <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-1/2 z-20 mt-1 w-48 -translate-x-1/2 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
            @foreach (['send' => ['envelope', 'Send email'], 'wait' => ['clock', 'Wait'], 'sms' => ['sms', 'Send SMS'], 'condition' => ['branch', 'Condition (yes / no)'], 'action' => ['tag', 'Action']] as $type => [$icon, $label])
                <form method="POST" action="{{ route('automations.steps.store', $automation) }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="parent" value="{{ $parent }}">
                    <input type="hidden" name="branch" value="{{ $branch }}">
                    <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-gray-100"><x-icon :name="$icon" class="size-4 text-gray-500" /> {{ $label }}</button>
                </form>
            @endforeach
        </div>
    </div>
    <div class="h-5 w-px bg-gray-300"></div>
</div>

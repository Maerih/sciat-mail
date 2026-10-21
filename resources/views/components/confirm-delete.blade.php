@props(['action', 'label' => 'Delete', 'message' => 'Delete this item? This cannot be undone.', 'class' => 'btn-ghost btn-sm text-red-600 hover:bg-red-50'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($message))" class="inline">
    @csrf
    @method('DELETE')
    <button type="submit" class="{{ $class }}">{{ $slot->isNotEmpty() ? $slot : $label }}</button>
</form>

<x-layouts.guest :title="$form->title ?: $form->list->name">
    <div class="card card-body p-7">
        <p class="mb-4 text-sm text-gray-500">{{ $form->list->workspace->company ?: $form->list->workspace->name }}</p>
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        @include('public.partials.form-embed')
    </div>
</x-layouts.guest>

<x-layouts.guest :title="$form->title ?: $form->list->name" :brand="$form->list->workspace">
    <div class="card card-body p-7">
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        @include('public.partials.form-embed')
    </div>
</x-layouts.guest>

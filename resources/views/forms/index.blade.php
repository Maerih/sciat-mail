<x-layouts.app title="Signup forms">
    <x-page-header title="Signup forms" subtitle="Embed a form on the client's website or share the hosted page — new contacts go straight into a list.">
        <x-slot:actions><a href="{{ route('forms.create') }}" class="btn-primary"><x-icon name="plus" /> New form</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        @if ($forms->isEmpty())
            <x-empty title="No forms yet" icon="form">Create a form, then paste its embed code into any website.
                <x-slot:action><a href="{{ route('forms.create') }}" class="btn-primary"><x-icon name="plus" /> New form</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Form</th><th>List</th><th>Status</th><th>Submissions</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($forms as $form)
                            <tr>
                                <td><a href="{{ route('forms.edit', $form) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $form->name }}</a></td>
                                <td>{{ $form->list->name }} <span class="badge-gray ml-1">{{ $form->isPopup() ? 'Popup' : 'Embedded' }}</span></td>
                                <td><x-status-badge :status="$form->status" /></td>
                                <td class="tabular-nums">{{ number_format($form->submissions) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('forms.embed', $form) }}" class="btn-ghost btn-sm">Embed code</a>
                                    <a href="{{ route('subscribe.show', $form->uid) }}" target="_blank" class="btn-ghost btn-sm">Hosted page</a>
                                    <x-confirm-delete :action="route('forms.destroy', $form)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $forms->links() }}
        @endif
    </div>
</x-layouts.app>

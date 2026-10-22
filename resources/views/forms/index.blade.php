<x-layouts.app title="Signup forms">
    <x-page-header title="Forms" subtitle="Design signup forms and embed them on your website. Every form connects to a list.">
        <x-slot:actions><a href="{{ route('forms.create') }}" class="btn-primary"><x-icon name="add" /> Create form</a></x-slot:actions>
    </x-page-header>
    <x-hero icon="web" title="Capture new subscribers" text="Embedded and popup forms add contacts to your lists automatically."
        :stats="[['web', $forms->total(), 'forms'], ['check_circle', $forms->where('status', 'published')->count(), 'published']]"
        :actions="[['edit_note', 'Create a signup form', 'Design a form that captures emails and subscriber data.', route('forms.create')],
                   ['language', 'Connect to website', 'Link your form to a website to start collecting subscribers.', route('websites.index')],
                   ['open_in_new', 'Popup forms', 'Show a modal, slide-in or bar on the client\'s website.', route('forms.create')]]" />
    <div class="card">
        @if ($forms->isEmpty())
            <x-empty title="No forms yet" icon="form">Create a form, then paste its embed code into any website.
                <x-slot:action><a href="{{ route('forms.create') }}" class="btn-primary"><x-icon name="plus" /> New form</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Form</th><th>List</th><th>Website</th><th>Status</th><th>Submissions</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($forms as $form)
                            <tr>
                                <td><a href="{{ route('forms.edit', $form) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $form->name }}</a></td>
                                <td>{{ $form->list->name }} <span class="badge-gray ml-1">{{ $form->isPopup() ? 'Popup' : 'Embedded' }}</span></td>
                                <td>@if ($form->website)<a href="{{ route('websites.show', $form->website) }}" class="inline-flex items-center gap-1 text-sm hover:text-brand-600"><x-icon name="language" class="size-4" /> {{ $form->website->name }}</a>@else<span class="inline-flex items-center gap-1 text-xs text-[var(--muted)]"><x-icon name="link_off" class="size-4" /> Not connected</span>@endif</td>
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

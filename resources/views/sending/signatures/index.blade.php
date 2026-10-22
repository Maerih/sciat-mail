<x-layouts.app title="Email signatures">
    <x-page-header title="Email signatures" subtitle="Create reusable email signatures for your campaigns.">
        <x-slot:actions><a href="{{ route('sending.signatures.create') }}" class="btn-primary"><x-icon name="add" /> Create signature</a></x-slot:actions>
    </x-page-header>
    <x-banner icon="draw" title="Email signatures" :text="'Save the signatures you use in your emails. Type {'.'{ SIGNATURE }'.'} in any email for the default one, or drag a Signature block in the builder — the email keeps its own copy.'"
        :stats="[['draw', $signatures->count().' '.Str::plural('signature', $signatures->count())], ['star', $signatures->firstWhere('is_default', true)?->name ?? 'No default yet']]" />
    <div class="card">
        @if ($signatures->isEmpty())
            <x-empty title="No signatures yet" icon="draw">Add your name, title, company and links once and reuse them in every email.
                <x-slot:action><a href="{{ route('sending.signatures.create') }}" class="btn-primary"><x-icon name="add" /> Create signature</a></x-slot:action>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Preview</th><th>Default</th><th>Updated</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($signatures as $signature)
                            <tr>
                                <td><a href="{{ route('sending.signatures.edit', $signature) }}" class="font-medium hover:text-brand-600">{{ $signature->name }}</a></td>
                                <td class="max-w-sm truncate text-[var(--muted)]">{{ Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($signature->html))), 70) }}</td>
                                <td>@if ($signature->is_default)<span class="badge-green">Default</span>@else
                                    <form method="POST" action="{{ route('sending.signatures.default', $signature) }}">@csrf<button class="text-xs text-brand-600 hover:underline">Make default</button></form>@endif</td>
                                <td class="text-[var(--muted)]">{{ $signature->updated_at->diffForHumans() }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('sending.signatures.edit', $signature) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4" /> Edit</a>
                                    <x-confirm-delete :action="route('sending.signatures.destroy', $signature)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

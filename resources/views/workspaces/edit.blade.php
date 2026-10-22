<x-layouts.app title="Client settings">
    <x-page-header title="Client settings" :subtitle="$workspace->name" />
    <form method="POST" action="{{ route('workspaces.update') }}" enctype="multipart/form-data" class="card card-body max-w-2xl space-y-5">
        @csrf @method('PUT')
        @include('workspaces._fields')
        <div class="border-t border-gray-100 pt-5">
            <h2 class="card-title">Client branding</h2>
            <p class="text-sm text-gray-500">Shown to this client's subscribers: signup and unsubscribe pages, confirmation and welcome emails, popup buttons.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Logo</label>
                    @if ($logo = \App\Services\Branding::clientLogo($workspace))
                        <img src="{{ $logo }}" alt="" class="mb-2 h-10 rounded bg-gray-50 object-contain p-1">
                        <label class="mb-2 flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove logo</label>
                    @endif
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm">
                    @error('logo')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-field name="brand_color" type="color" label="Brand colour" :value="$workspace->brand_color ?? \App\Services\Branding::appColor()" class="[&_input]:h-10 [&_input]:p-1" />
                <x-field name="website" type="url" label="Website" :value="$workspace->website" placeholder="https://client.com" class="sm:col-span-2" />
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <div class="border-t border-gray-100 pt-5">
                <h2 class="card-title">Limits</h2>
                <p class="text-sm text-gray-500">Leave empty for unlimited. Campaigns pause when the monthly email limit is reached; new contacts are refused at the contact limit.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-field name="monthly_email_limit" type="number" min="0" label="Emails per month" :value="$workspace->monthly_email_limit" />
                    <x-field name="contact_limit" type="number" min="0" label="Contacts" :value="$workspace->contact_limit" />
                </div>
            </div>
        @endif
        <div class="flex justify-end"><button class="btn-primary">Save settings</button></div>
    </form>

    @if (auth()->user()->isAdmin())
        <form method="POST" action="{{ route('workspaces.destroy') }}" class="card card-body mt-6 max-w-2xl space-y-3 border-red-200">
            @csrf @method('DELETE')
            <h2 class="text-sm font-semibold text-red-700">Delete this client</h2>
            <p class="text-sm text-gray-600">Permanently deletes every list, subscriber, campaign, report and domain of this client.</p>
            <x-field name="confirm" :label="'Type “'.$workspace->name.'” to confirm'" />
            <button class="btn-danger">Delete client</button>
        </form>
    @endif
</x-layouts.app>

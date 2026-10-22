<x-layouts.app :title="$list->exists ? 'List settings' : 'New list'">
    @if ($list->exists)
        <x-page-header :title="$list->name" :back="route('lists.index')" />
        @include('lists._tabs')
    @else
        <x-page-header title="Create a list" :back="route('lists.index')" />
    @endif

    <form method="POST" action="{{ $list->exists ? route('lists.update', $list) : route('lists.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($list->exists) @method('PUT') @endif

        <div class="card card-body space-y-4">
            <h2 class="card-title">List details</h2>
            <x-field name="name" label="List name" :value="$list->name" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="from_name" label="Default from name" :value="$list->from_name" required />
                <x-field name="from_email" type="email" label="Default from email" :value="$list->from_email" required help="Use an address on a verified sending domain." />
                <x-field name="reply_to" type="email" label="Reply-to email" :value="$list->reply_to" />
                <div>
                    <label class="label">Default sending server</label>
                    <select name="default_sending_server_id" class="input">
                        <option value="">Rotate across all available servers</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}" @selected(old('default_sending_server_id', $list->default_sending_server_id) == $server->id)>{{ $server->name }} ({{ $server->typeLabel() }})</option>
                        @endforeach
                    </select>
                    <p class="help">Used for confirmation and welcome emails.</p>
                </div>
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="card-title">Compliance footer</h2>
            <p class="-mt-2 text-sm text-gray-500">Shown in every campaign footer. Leave blank to use the client's details.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="company" label="Company" :value="$list->company" />
                <x-field name="address" label="Postal address" :value="$list->address" />
            </div>
        </div>

        @if ($list->exists)
            <div class="card card-body space-y-3">
                <h2 class="card-title">SMS</h2>
                <div><label class="label">Phone number field</label>
                    <select name="phone_field" class="input">
                        <option value="">Automatic{{ $list->phoneTag() ? ' ('.$list->phoneTag().')' : ' — no phone field found' }}</option>
                        @foreach ($list->fields as $field)<option value="{{ $field->tag }}" @selected($list->phone_field === $field->tag)>{{ $field->label }} ({{ $field->tag }})</option>@endforeach
                    </select>
                    <p class="help">SMS campaigns and automations text the number in this field. Add a field of type “Phone number” under Fields if the list has none.</p></div>
            </div>
        @endif

        <div class="card card-body space-y-4" x-data="{ welcome: @js((bool) old('send_welcome_email', $list->send_welcome_email)), notify: @js((bool) old('unsubscribe_notification', $list->unsubscribe_notification)) }">
            <h2 class="card-title">Subscription settings</h2>
            <x-toggle name="double_optin" label="Double opt-in" help="Subscribers must click a confirmation link before they receive campaigns." :checked="$list->double_optin" />
            <x-toggle name="send_welcome_email" label="Send welcome email" help="Sent automatically once someone subscribes (or confirms)." :checked="$list->send_welcome_email" x-model="welcome" />
            <div x-show="welcome" x-cloak class="space-y-3 border-l-2 border-gray-100 pl-6">
                <x-field name="welcome_subject" label="Welcome subject" :value="$list->welcome_subject" :placeholder="'Welcome to '.($list->name ?: 'our list')" />
                <x-field name="welcome_html" type="textarea" label="Welcome message (HTML allowed)" :value="$list->welcome_html" rows="5" help="Merge tags such as &#123;&#123; SUBSCRIBER_FIRST_NAME | there &#125;&#125; work here." />
            </div>
            <x-toggle name="unsubscribe_notification" label="Unsubscribe notification" help="Email the team when someone unsubscribes from this list." :checked="$list->unsubscribe_notification" x-model="notify" />
            <div x-show="notify" x-cloak class="border-l-2 border-gray-100 pl-6">
                <x-field name="notification_email" type="email" label="Notify address" :value="$list->notification_email" help="Defaults to the client's contact email." />
            </div>
        </div>

        <div class="flex items-center justify-between">
            @if ($list->exists)
                <x-confirm-delete :action="route('lists.destroy', $list)" label="Delete list" :message="'Delete '.$list->name.' and all of its subscribers?'" class="btn-ghost text-red-600 hover:bg-red-50" />
            @else
                <span></span>
            @endif
            <button class="btn-primary">{{ $list->exists ? 'Save settings' : 'Create list' }}</button>
        </div>
    </form>
</x-layouts.app>

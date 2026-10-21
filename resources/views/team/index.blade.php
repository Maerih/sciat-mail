<x-layouts.app title="Team">
    <x-page-header title="Team" subtitle="People who can log in and manage every client." />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="{{ $user->isAdmin() ? 'badge-purple' : 'badge-gray' }}">{{ ucfirst($user->role) }}</span></td>
                            <td class="text-right">
                                @unless ($user->is(auth()->user()))
                                    <x-confirm-delete :action="route('team.destroy', $user)" label="Remove" :message="'Remove '.$user->name.'?'" />
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('team.store') }}" class="card card-body space-y-4">
            @csrf
            <h2 class="card-title">Add team member</h2>
            <x-field name="name" label="Name" required />
            <x-field name="email" type="email" label="Email" required />
            <x-field name="password" type="password" label="Temporary password" help="At least 10 characters. Ask them to change it." required />
            <div>
                <label class="label">Role</label>
                <select name="role" class="input"><option value="member">Member</option><option value="admin">Admin</option></select>
                <p class="help">Admins can manage clients, the team and shared sending servers.</p>
            </div>
            <button class="btn-primary w-full">Add member</button>
        </form>
    </div>
</x-layouts.app>

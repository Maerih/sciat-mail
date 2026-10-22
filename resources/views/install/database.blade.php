<x-install-layout title="Database" step="database">
    <form method="POST" action="{{ route('install.database.save') }}" class="card">
        @csrf
        <div class="card-header"><div><h2 class="card-title">Database</h2><p class="text-xs text-[var(--muted)]">Create a MySQL/MariaDB database and user in your hosting panel (cPanel → MySQL Databases), give the user all privileges, then enter the details here.</p></div></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-field name="host" label="Host" :value="$db['host'] ?? 'localhost'" class="sm:col-span-2" required help="Usually localhost on shared hosting." />
            <x-field name="port" type="number" label="Port" :value="$db['port'] ?? 3306" required />
            <x-field name="database" label="Database name" :value="old('database')" required class="sm:col-span-3" help="On cPanel it is prefixed with your account name, e.g. myacct_mail." />
            <x-field name="username" label="Database user" :value="old('username')" required class="sm:col-span-3" />
            <x-field name="password" type="password" label="Password" class="sm:col-span-3" autocomplete="new-password" />
        </div>
        <div class="flex justify-between border-t border-[var(--line)] px-6 py-4">
            <a href="{{ route('install.requirements') }}" class="btn-secondary"><x-icon name="arrow_back" class="size-4.5" /> Back</a>
            <button class="btn-primary">Connect and create tables <x-icon name="arrow_forward" class="size-4.5" /></button>
        </div>
    </form>
    <p class="mt-3 text-xs text-[var(--muted)]">Creating the tables can take up to a minute — keep this page open.</p>
</x-install-layout>

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EnvWriter;
use App\Services\Requirements;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\WarmupStrategySeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Web installer for hosting without a terminal: checks the server, saves the database
 * settings, runs migrations and creates the first administrator. Only reachable while
 * APP_INSTALLER=true; it switches itself off when done.
 */
class InstallController extends Controller
{
    public function requirements(): View
    {
        return view('install.requirements', ['checks' => Requirements::check(), 'passes' => Requirements::passes()]);
    }

    public function database(): View|RedirectResponse
    {
        if (! Requirements::passes()) {
            return redirect()->route('install.requirements');
        }

        return view('install.database', ['db' => config('database.connections.mysql')]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => 'required|string|max:190',
            'port' => 'required|integer|between:1,65535',
            'database' => 'required|string|max:64',
            'username' => 'required|string|max:64',
            'password' => 'nullable|string|max:190',
        ]);

        config(['database.default' => 'mysql', 'database.connections.mysql' => array_merge(config('database.connections.mysql'), $data + ['password' => ''])]);
        DB::purge('mysql');
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            return back()->withInput($request->except('password'))
                ->withErrors(['host' => 'Could not connect: '.$this->friendly($e->getMessage())]);
        }

        EnvWriter::set([
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['host'], 'DB_PORT' => $data['port'],
            'DB_DATABASE' => $data['database'], 'DB_USERNAME' => $data['username'], 'DB_PASSWORD' => $data['password'] ?? '',
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
            (new TemplateSeeder)->run();
            (new WarmupStrategySeeder)->run();
        } catch (\Throwable $e) {
            return back()->withErrors(['host' => 'Connected, but setting up the tables failed: '.$this->friendly($e->getMessage())]);
        }

        try {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        } catch (\Throwable) {
            // symlinks disabled: uploads are served through the storage fallback route
        }

        return redirect()->route('install.admin');
    }

    public function admin(): View|RedirectResponse
    {
        if (! $this->databaseReady()) {
            return redirect()->route('install.database');
        }

        return view('install.admin', ['alreadyInstalled' => User::where('role', 'admin')->exists()]);
    }

    public function saveAdmin(Request $request): View|RedirectResponse
    {
        if (! $this->databaseReady()) {
            return redirect()->route('install.database');
        }
        abort_if(User::where('role', 'admin')->exists(), 403, 'An administrator already exists. Set APP_INSTALLER=false in .env.');

        $data = $request->validate([
            'app_name' => 'required|string|max:60',
            'app_url' => 'required|url',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'admin']);
        EnvWriter::set([
            'APP_NAME' => $data['app_name'], 'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_INSTALLER' => false,
        ]);

        return view('install.done', ['cron' => $this->cronCommand(), 'url' => rtrim($data['app_url'], '/')]);
    }

    protected function databaseReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('migrations');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function cronCommand(): string
    {
        $php = PHP_BINARY && ! preg_match('/fpm|lsphp|cgi/i', PHP_BINARY) ? PHP_BINARY : '/usr/local/bin/php';

        return '* * * * * '.$php.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }

    protected function friendly(string $message): string
    {
        // Hide the SQL/connection noise, keep the driver's explanation
        return preg_replace('/\s*\(Connection: .*$|SQLSTATE\[\w+\]\s*(\[\d+\])?\s*/', '', $message);
    }
}

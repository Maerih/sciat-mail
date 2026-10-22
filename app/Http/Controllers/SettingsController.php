<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Workspace;
use App\Services\Branding;
use App\Services\DatabaseBackup;
use App\Services\Requirements;
use App\Services\SendingHealth;
use App\Services\SystemMailConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrator settings hub. Each tab edits one group of platform-wide settings.
 */
class SettingsController extends Controller
{
    public const TABS = [
        'general' => ['General', 'tune'],
        'mail' => ['System mail', 'outgoing_mail'],
        'sending' => ['Sending defaults', 'send'],
        'health' => ['Sending health', 'health_and_safety'],
        'ai' => ['AI assistant', 'auto_awesome'],
        'cron' => ['Cron & queue', 'schedule'],
        'maintenance' => ['Maintenance', 'build'],
        'system' => ['System info', 'info'],
    ];

    /** Sending defaults with their fallback values. */
    public const SENDING_DEFAULTS = [
        'one_click_unsubscribe' => true,
        'use_global_blacklist' => true,
        'default_double_optin' => false,
        'default_skip_failed' => true,
        'default_track_open' => true,
        'default_track_click' => true,
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.show', 'general');
    }

    public function show(string $tab): View|RedirectResponse
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        if ($tab === 'ai') {
            return redirect()->route('platform.ai');
        }

        return view('settings.'.$tab, ['tab' => $tab, 'tabs' => self::TABS] + $this->data($tab));
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => 'nullable|string|max:60',
            'platform_description' => 'nullable|string|max:255',
            'platform_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_theme' => 'required|in:dark,light',
            'offline_message' => 'nullable|string|max:500',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'favicon' => 'nullable|mimes:png,ico,svg|max:512',
        ]);

        $values = collect($data)->only(['platform_name', 'platform_description', 'platform_color', 'default_theme', 'offline_message'])->all();
        $values['site_online'] = $request->boolean('site_online') ? '1' : '0';
        foreach (['logo' => 'platform_logo', 'favicon' => 'platform_favicon'] as $field => $key) {
            if ($request->hasFile($field)) {
                $values[$key] = $request->file($field)->store('branding', 'public');
            } elseif ($request->boolean('remove_'.$field)) {
                $values[$key] = null;
            }
        }
        Setting::put($values);

        return back()->with('success', 'General settings saved.');
    }

    public function updateSending(Request $request): RedirectResponse
    {
        Setting::put(collect(self::SENDING_DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => $request->boolean($key) ? '1' : '0'])->all());

        return back()->with('success', 'Sending defaults saved.');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_driver' => ['required', Rule::in(array_keys(SystemMailConfig::DRIVERS))],
            'mail_host' => 'required_if:mail_driver,smtp|nullable|string|max:190',
            'mail_port' => 'required_if:mail_driver,smtp|nullable|integer|between:1,65535',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_username' => 'required_if:mail_driver,ses|nullable|string|max:190',
            'mail_password' => 'nullable|string|max:500',
            'mail_region' => 'nullable|string|max:40',
            'mail_sendmail_path' => 'nullable|string|max:190',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'nullable|string|max:120',
        ]);

        $values = collect($data)->except('mail_password')->all();
        if (filled($data['mail_password'] ?? null)) {
            $values['mail_password'] = Crypt::encryptString($data['mail_password']); // blank keeps the stored secret
        }
        Setting::put($values);

        return back()->with('success', 'System mail settings saved.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        SystemMailConfig::apply();
        try {
            Mail::raw('This is a test email from '.Branding::appName().'. System mail is working.', function ($m) use ($request) {
                $m->to($request->user()->email)->subject('System mail test');
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Sending failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$request->user()->email.'.');
    }

    public function updateHealth(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'health_mode' => ['required', Rule::in(array_keys(SendingHealth::MODES))],
            'health_bounce_warn' => 'required|numeric|between:0.1,100',
            'health_bounce_suspend' => 'required|numeric|between:0.1,100|gte:health_bounce_warn',
            'health_complaint_warn' => 'required|numeric|between:0.01,100',
            'health_complaint_suspend' => 'required|numeric|between:0.01,100|gte:health_complaint_warn',
            'health_window_days' => 'required|integer|between:1,90',
            'health_min_sent' => 'required|integer|between:1,1000000',
        ]);
        Setting::put($data);

        return back()->with('success', 'Sending health settings saved.');
    }

    public function liftSuspension(Workspace $workspace): RedirectResponse
    {
        SendingHealth::lift($workspace);

        return back()->with('success', 'Sending resumed for '.$workspace->name.'. Paused campaigns can be resumed from their pages.');
    }

    public function maintenance(Request $request, DatabaseBackup $backups): RedirectResponse
    {
        $request->validate(['action' => ['required', Rule::in(['migrate', 'clear_cache', 'backup', 'backup_settings'])]]);

        switch ($request->action) {
            case 'migrate':
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('optimize:clear');

                return back()->with('success', 'Database updated and caches cleared.');
            case 'clear_cache':
                Artisan::call('optimize:clear');

                return back()->with('success', 'Caches cleared.');
            case 'backup':
                $path = $backups->create();

                return back()->with('success', 'Backup created: '.basename($path).'.');
            default:
                $data = $request->validate(['backup_keep' => 'required|integer|between:1,60']);
                Setting::put(['backups_enabled' => $request->boolean('backups_enabled') ? '1' : '0', 'backup_keep' => $data['backup_keep']]);

                return back()->with('success', 'Backup settings saved.');
        }
    }

    public function downloadBackup(string $name)
    {
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name) && is_file(DatabaseBackup::directory().'/'.$name), 404);

        return response()->download(DatabaseBackup::directory().'/'.$name);
    }

    public function deleteBackup(string $name): RedirectResponse
    {
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name), 404);
        @unlink(DatabaseBackup::directory().'/'.$name);

        return back()->with('success', 'Backup deleted.');
    }

    public function cron(Request $request): RedirectResponse
    {
        $request->validate(['action' => ['required', Rule::in(['retry_failed', 'flush_failed'])]]);

        if ($request->action === 'retry_failed') {
            Artisan::call('queue:retry', ['id' => ['all']]);

            return back()->with('success', 'Failed jobs were pushed back onto the queue.');
        }
        Artisan::call('queue:flush');

        return back()->with('success', 'Failed jobs were deleted.');
    }

    /** @return array<string, mixed> */
    protected function data(string $tab): array
    {
        return match ($tab) {
            'general' => ['s' => fn (string $key, $default = null) => Setting::get($key, $default)],
            'mail' => ['s' => fn (string $key, $default = null) => Setting::get($key, $default), 'hasSecret' => filled(Setting::get('mail_password'))],
            'sending' => ['values' => collect(self::SENDING_DEFAULTS)->map(fn ($default, $key) => Setting::bool($key, $default))->all()],
            'health' => [
                'config' => collect(SendingHealth::DEFAULTS)->map(fn ($default, $key) => SendingHealth::config($key))->all(),
                'workspaces' => Workspace::orderBy('name')->get()->map(fn (Workspace $w) => ['workspace' => $w, 'health' => SendingHealth::measure($w)]),
            ],
            'cron' => $this->cronData(),
            'maintenance' => [
                'pending' => DatabaseBackup::pendingMigrations(),
                'backups' => DatabaseBackup::all(),
                'backupsEnabled' => Setting::bool('backups_enabled', true),
                'backupKeep' => (int) Setting::get('backup_keep', 7),
                'version' => is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : null,
            ],
            'system' => $this->systemData(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function cronData(): array
    {
        $beat = fn (string $key) => ($v = Cache::get($key)) ? Carbon::parse($v) : null;
        // Under PHP-FPM/LiteSpeed PHP_BINARY is the web SAPI, not the CLI binary cron needs
        $php = PHP_BINARY && ! preg_match('/fpm|lsphp|cgi/i', PHP_BINARY) ? PHP_BINARY : '/usr/local/bin/php';
        $size = null;
        try {
            $size = Queue::size();
        } catch (\Throwable) {
            // queue backend unreachable — shown as unknown
        }

        return [
            'command' => '* * * * * '.$php.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
            'worker' => 'php '.base_path('artisan').' queue:work --tries=3 --timeout=900',
            'cronWorker' => config('queue.cron_worker'),
            'scheduler' => $beat('heartbeat:scheduler'),
            'workerBeat' => $beat('heartbeat:worker'),
            'queueSize' => $size,
            'failed' => DB::table('failed_jobs')->count(),
            'recentFailed' => DB::table('failed_jobs')->latest('failed_at')->limit(5)->get(['id', 'queue', 'payload', 'exception', 'failed_at']),
        ];
    }

    /** @return array<string, mixed> */
    protected function systemData(): array
    {
        $required = Requirements::EXTENSIONS;
        if (in_array('redis', [config('queue.default'), config('cache.default'), config('session.driver')], true)) {
            $required[] = 'redis';
        }
        // shell_exec is often disabled on shared hosting
        $version = function_exists('shell_exec') ? trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --short HEAD 2>/dev/null')) : '';
        $version = $version ?: (is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : 'unknown');
        $free = @disk_free_space(storage_path());

        return [
            'rows' => [
                'Application' => [
                    'Version' => $version,
                    'Environment' => app()->environment(),
                    'Debug mode' => config('app.debug') ? 'On (turn off in production)' : 'Off',
                    'Application URL' => config('app.url'),
                    'Timezone' => config('app.timezone'),
                ],
                'Server' => [
                    'PHP' => PHP_VERSION,
                    'Laravel' => app()->version(),
                    'Database' => config('database.default').' '.(DB::selectOne('select version() as v')->v ?? ''),
                    'Cache store' => config('cache.default'),
                    'Queue connection' => config('queue.default'),
                    'Free disk space' => $free ? round($free / 1024 ** 3, 1).' GB' : 'unknown',
                    'Public storage link' => is_link(public_path('storage')) ? 'OK' : 'Not linked — files are served by the app instead (slower; run php artisan storage:link if your host allows symlinks)',
                    'Queue processing' => config('queue.cron_worker') ? 'Cron (shared hosting mode)' : 'Background worker',
                    'Disabled PHP functions' => collect(['proc_open', 'exec', 'shell_exec', 'symlink', 'putenv'])->reject(fn ($f) => function_exists($f))->implode(', ') ?: 'None of the ones we use',
                ],
            ],
            'extensions' => collect($required)->mapWithKeys(fn ($ext) => [$ext => extension_loaded($ext)])->all(),
            'storageWritable' => is_writable(storage_path()) && Storage::disk('local')->put('.write-test', 'ok'),
        ];
    }
}

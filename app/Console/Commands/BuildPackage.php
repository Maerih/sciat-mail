<?php

namespace App\Console\Commands;

use App\Services\EnvWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds a zip for hosting without a terminal: committed code, production dependencies,
 * built assets and a fresh .env that starts the web installer.
 */
#[Signature('app:package {--update : Build an update zip without .env and storage, for uploading over a live site} {--allow-dirty : Package even with uncommitted changes}')]
#[Description('Build an upload-ready zip for shared hosting (runs the web installer on first visit)')]
class BuildPackage extends Command
{
    /** Development-only paths left out of the package. */
    protected const EXCLUDE = ['tests', '.ai', '.claude', '.github', 'docs', 'node_modules', 'AGENTS.md', 'CLAUDE.md', 'boost.json', '.mcp.json', 'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js', '.npmrc', '.editorconfig'];

    public function handle(): int
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->error('Built assets are missing — run "npm run build" first.');

            return self::FAILURE;
        }
        $dirty = trim(Process::path(base_path())->run('git status --porcelain')->output());
        if ($dirty !== '' && ! $this->option('allow-dirty')) {
            $this->error('There are uncommitted changes; the package is built from the last commit. Commit first or pass --allow-dirty.');

            return self::FAILURE;
        }

        // v1.0.0 on a tagged commit, v1.0.0-3-gabc1234 after it
        $version = trim(Process::path(base_path())->run('git describe --tags --always')->output()) ?: now()->format('Ymd');
        $work = storage_path('app/package-build/'.Str::random(8));
        File::ensureDirectoryExists($work);

        try {
            $this->components->task('Exporting committed code', fn () => Process::path(base_path())
                ->run('git archive HEAD | tar -x -C '.escapeshellarg($work))->throw());
            $this->components->task('Copying built assets', fn () => File::copyDirectory(public_path('build'), $work.'/public/build'));
            $this->components->task('Installing production dependencies', fn () => Process::path($work)->timeout(600)
                ->run('composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress')->throw());
            $this->components->task('Writing production configuration', fn () => $this->writeProductionConfig($work, $version));

            // An update must never overwrite the live site's settings or uploads
            $exclude = $this->option('update') ? [...self::EXCLUDE, '.env', 'storage'] : self::EXCLUDE;
            foreach ($exclude as $path) {
                File::isDirectory("{$work}/{$path}") ? File::deleteDirectory("{$work}/{$path}") : File::delete("{$work}/{$path}");
            }

            $zip = storage_path('app/packages/sciat-mail-'.($this->option('update') ? 'update-' : '').$version.'.zip');
            $this->components->task('Creating zip', fn () => $this->zip($work, $zip));
        } finally {
            File::deleteDirectory($work);
        }

        $this->newLine();
        $this->components->info('Package ready: '.$zip.' ('.round(filesize($zip) / 1048576, 1).' MB)');

        return self::SUCCESS;
    }

    protected function writeProductionConfig(string $work, string $version): void
    {
        $env = "{$work}/.env";
        $example = (string) file_get_contents("{$work}/.env.example");
        // Drop the seeder-only admin block; the installer creates the administrator
        file_put_contents($env, preg_replace('/\n# First administrator.*$/s', "\n", $example));

        EnvWriter::set([
            'APP_NAME' => 'SCIAT Mail',
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => false,
            'APP_URL' => 'http://localhost',
            'APP_INSTALLER' => true,
            'LOG_LEVEL' => 'error',
            'DB_CONNECTION' => 'mysql',
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'QUEUE_CRON_WORKER' => true,
            'MAIL_MAILER' => 'smtp',
        ], $env);

        file_put_contents("{$work}/VERSION", $version."\n");
        // For hosts where the web root can't point at public/: send every request into it
        // Even without mod_rewrite, never serve .env, logs, code or dependencies from the app root
        file_put_contents("{$work}/.htaccess", implode("\n", [
            'Options -Indexes',
            '<FilesMatch "^\\.|\\.(env|log|sql|gz|lock)$|^artisan$">',
            '    Require all denied',
            '</FilesMatch>',
            '<IfModule mod_rewrite.c>',
            '    RewriteEngine On',
            '    RewriteRule ^(.*)$ public/$1 [L]',
            '</IfModule>',
        ])."\n");
    }

    protected function zip(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        File::delete($target);
        $zip = new ZipArchive;
        $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            $relative = 'sciat-mail/'.substr($file->getPathname(), strlen($source) + 1);
            $file->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
    }
}

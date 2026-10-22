<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Plain-PHP SQL dump (no mysqldump needed, so it works on shared hosting). Files are gzip
 * SQL that phpMyAdmin or the mysql client can import directly.
 */
class DatabaseBackup
{
    /** Tables whose rows are throwaway and left out of backups. */
    public const SKIP_DATA = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    public function create(): string
    {
        File::ensureDirectoryExists(static::directory());
        $path = static::directory().'/backup-'.now()->format('Ymd-His').'.sql.gz';
        $out = gzopen($path, 'wb6');
        $pdo = DB::connection()->getPdo();

        gzwrite($out, '-- Database backup '.now()->toDateTimeString()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            gzwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            $batch = [];
            $flush = function () use (&$batch, $out, $table) {
                if ($batch) {
                    gzwrite($out, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            };
            $rows = DB::getSchemaBuilder()->hasColumn($table, 'id') ? DB::table($table)->lazyById(500) : DB::table($table)->cursor();
            foreach ($rows as $record) {
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record)).')';
                if (count($batch) >= 200) {
                    $flush();
                }
            }
            $flush();
            gzwrite($out, "\n");
        }
        gzwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($out);

        $this->prune();

        return $path;
    }

    /** Keeps the newest N backups (Setting backup_keep, default 7). */
    public function prune(): void
    {
        $keep = max(1, (int) Setting::get('backup_keep', 7));
        static::all()->slice($keep)->each(fn ($backup) => File::delete($backup['path']));
    }

    /** @return Collection<int, array{name: string, path: string, size: int, created: int}> */
    public static function all()
    {
        return collect(File::glob(static::directory().'/backup-*.sql.gz'))
            ->map(fn ($path) => ['name' => basename($path), 'path' => $path, 'size' => filesize($path), 'created' => filemtime($path)])
            ->sortByDesc('name')->values();
    }

    public static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return [];
        }
        $files = $migrator->getMigrationFiles([database_path('migrations')]);

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }
}

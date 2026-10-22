<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backup')]
#[Description('Write a gzip SQL backup of the database to storage/app/backups (keeps the newest 7)')]
class BackupDatabase extends Command
{
    public function handle(DatabaseBackup $backup): int
    {
        $path = $backup->create();
        $this->components->info('Backup written: '.$path.' ('.round(filesize($path) / 1024).' KB)');

        return self::SUCCESS;
    }
}

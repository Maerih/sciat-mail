<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(DatabaseBackup::directory());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(DatabaseBackup::directory());
        parent::tearDown();
    }

    public function test_admin_creates_downloads_and_deletes_a_backup(): void
    {
        User::factory()->create(['email' => 'in-backup@example.com']);
        $this->signInTo(Workspace::factory()->create(), 'admin');

        $this->post(route('settings.maintenance'), ['action' => 'backup'])->assertSessionHas('success');
        $backup = DatabaseBackup::all()->sole();

        $sql = gzdecode(file_get_contents($backup['path']));
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('in-backup@example.com', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);

        $this->get(route('settings.backups.download', $backup['name']))->assertOk()->assertDownload($backup['name']);
        $this->get(route('settings.show', 'maintenance'))->assertOk()->assertSee($backup['name'])->assertSee('Up to date');

        $this->delete(route('settings.backups.delete', $backup['name']))->assertRedirect();
        $this->assertCount(0, DatabaseBackup::all());
    }

    public function test_old_backups_are_pruned(): void
    {
        Setting::put(['backup_keep' => 2]);
        File::ensureDirectoryExists(DatabaseBackup::directory());
        foreach (['20260101-010101', '20260102-010101', '20260103-010101'] as $stamp) {
            file_put_contents(DatabaseBackup::directory()."/backup-{$stamp}.sql.gz", gzencode('-- old'));
        }

        app(DatabaseBackup::class)->prune();

        $this->assertSame(['backup-20260103-010101.sql.gz', 'backup-20260102-010101.sql.gz'], DatabaseBackup::all()->pluck('name')->all());
    }

    public function test_backup_command_runs_from_cron(): void
    {
        $this->artisan('app:backup')->assertSuccessful();
        $this->assertCount(1, DatabaseBackup::all());
    }

    public function test_maintenance_is_admin_only_and_file_names_are_validated(): void
    {
        $this->signInTo(Workspace::factory()->create());
        $this->post(route('settings.maintenance'), ['action' => 'clear_cache'])->assertForbidden();

        $this->signInTo(Workspace::factory()->create(), 'admin');
        $this->get('/settings/backups/..%2F..%2F.env')->assertNotFound();
        $this->get(route('settings.backups.download', 'backup-20260101-010101.sql.gz'))->assertNotFound();
        $this->post(route('settings.maintenance'), ['action' => 'migrate'])->assertSessionHas('success');
        $this->assertSame([], DatabaseBackup::pendingMigrations());
    }
}

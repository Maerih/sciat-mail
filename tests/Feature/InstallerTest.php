<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EnvWriter;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected string $envDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envDir = sys_get_temp_dir().'/installer-test-'.uniqid();
        mkdir($this->envDir);
        file_put_contents($this->envDir.'/.env', "APP_NAME=Laravel\nAPP_INSTALLER=true\n# DB_HOST=127.0.0.1\n");
        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->envDir.'/.env');
        @rmdir($this->envDir);
        parent::tearDown();
    }

    protected function env(): array
    {
        return Dotenv::parse((string) file_get_contents($this->envDir.'/.env'));
    }

    public function test_installer_is_hidden_unless_enabled(): void
    {
        config(['app.installer' => false]);
        $this->get('/install')->assertNotFound();
    }

    public function test_every_page_leads_to_the_installer_while_enabled(): void
    {
        config(['app.installer' => true]);
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Server check');
    }

    public function test_database_step_rejects_bad_credentials_and_saves_good_ones(): void
    {
        config(['app.installer' => true]);
        $db = config('database.connections.mysql');

        $this->post(route('install.database.save'), ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username'], 'password' => 'wrong-password-xyz'])
            ->assertSessionHasErrors('host');
        $this->assertArrayNotHasKey('DB_DATABASE', $this->env());

        $this->post(route('install.database.save'), ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username'], 'password' => $db['password']])
            ->assertRedirect(route('install.admin'));
        $this->assertSame($db['database'], $this->env()['DB_DATABASE']);
        $this->assertSame('mysql', $this->env()['DB_CONNECTION']);
    }

    public function test_admin_step_creates_the_administrator_and_switches_the_installer_off(): void
    {
        config(['app.installer' => true]);

        $this->post(route('install.admin.save'), [
            'app_name' => 'Acme Mail', 'app_url' => 'https://mail.acme.test/', 'name' => 'Pat', 'email' => 'pat@acme.test',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertOk()->assertSee('Installation complete')->assertSee('schedule:run');

        $admin = User::where('email', 'pat@acme.test')->sole();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('a-long-password', $admin->password));
        $this->assertSame('false', $this->env()['APP_INSTALLER']);
        $this->assertSame('Acme Mail', $this->env()['APP_NAME']);
        $this->assertSame('https://mail.acme.test', $this->env()['APP_URL']);

        // A second run can't create another administrator
        $this->post(route('install.admin.save'), ['app_name' => 'X', 'app_url' => 'https://x.test', 'name' => 'Eve', 'email' => 'eve@x.test', 'password' => 'another-password', 'password_confirmation' => 'another-password'])
            ->assertForbidden();
    }

    public function test_env_writer_round_trips_awkward_values(): void
    {
        $path = $this->envDir.'/.env';
        EnvWriter::set(['DB_PASSWORD' => 'p@ss w"rd $HOME #1', 'APP_NAME' => 'Acme Mail', 'APP_DEBUG' => false, 'EMPTY' => ''], $path);
        EnvWriter::set(['APP_NAME' => 'Renamed'], $path);

        $env = $this->env();
        $this->assertSame('p@ss w"rd $HOME #1', $env['DB_PASSWORD']);
        $this->assertSame('Renamed', $env['APP_NAME']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('', $env['EMPTY']);
        $this->assertStringContainsString('# DB_HOST=127.0.0.1', file_get_contents($path), 'comments are kept');
        $this->assertSame(1, substr_count(file_get_contents($path), 'APP_NAME='));
    }
}

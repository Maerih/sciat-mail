<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the first administrator from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD in .env
     * and loads the starter templates.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if ($email && $password) {
            User::firstOrCreate(['email' => $email], [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => $password,
                'role' => 'admin',
            ]);
        } else {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env — no admin user created.');
        }

        $this->call([TemplateSeeder::class, WarmupStrategySeeder::class]);
    }
}

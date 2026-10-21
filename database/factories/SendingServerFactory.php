<?php

namespace Database\Factories;

use App\Models\SendingServer;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SendingServer> */
class SendingServerFactory extends Factory
{
    protected $model = SendingServer::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'SMTP '.fake()->word(),
            'type' => 'smtp',
            'config' => ['host' => '127.0.0.1', 'port' => 2525, 'encryption' => 'none', 'username' => null, 'password' => null],
            'status' => 'active',
        ];
    }

    public function shared(): static
    {
        return $this->state(['workspace_id' => null]);
    }

    public function ofType(string $type, array $config): static
    {
        return $this->state(['type' => $type, 'config' => $config]);
    }
}

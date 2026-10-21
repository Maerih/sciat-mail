<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workspace> */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'timezone' => 'UTC',
            'company' => fake()->company(),
            'address' => fake()->address(),
            'contact_email' => fake()->safeEmail(),
        ];
    }
}

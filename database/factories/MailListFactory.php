<?php

namespace Database\Factories;

use App\Models\MailList;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MailList> */
class MailListFactory extends Factory
{
    protected $model = MailList::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'from_name' => fake()->company(),
            'from_email' => fake()->safeEmail(),
        ];
    }

    public function doubleOptIn(): static
    {
        return $this->state(['double_optin' => true]);
    }
}

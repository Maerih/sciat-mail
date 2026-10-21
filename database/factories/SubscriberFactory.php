<?php

namespace Database\Factories;

use App\Models\MailList;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscriber> */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'mail_list_id' => MailList::factory(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'subscribed',
            'data' => ['FIRST_NAME' => fake()->firstName(), 'LAST_NAME' => fake()->lastName()],
            'source' => 'manual',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}

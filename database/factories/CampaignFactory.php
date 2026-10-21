<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->sentence(3),
            'type' => 'regular',
            'subject' => 'Hello {{ SUBSCRIBER_FIRST_NAME | friend }}',
            'from_name' => 'Sender',
            'from_email' => 'news@example.com',
            'reply_to' => 'reply@example.com',
            'html' => '<html><body><p>Hi {{ SUBSCRIBER_FIRST_NAME }}</p><a href="https://example.com/offer">Offer</a></body></html>',
        ];
    }
}

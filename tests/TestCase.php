<?php

namespace Tests;

use App\Models\SendingServer;
use App\Models\User;
use App\Models\Workspace;
use App\Sending\TransportFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Support\RecordingTransport;

abstract class TestCase extends BaseTestCase
{
    protected RecordingTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        Workspace::setCurrent(null);

        // Every sending server delivers into memory instead of the network
        $this->transport = new RecordingTransport;
        $recording = $this->transport;
        $this->app->instance(TransportFactory::class, new class($recording) extends TransportFactory
        {
            public function __construct(private RecordingTransport $recording) {}

            public function for(SendingServer $server): TransportInterface
            {
                return $this->recording;
            }

            public function make(SendingServer $server): TransportInterface
            {
                return $this->recording;
            }
        });
    }

    /** Signs in a team member whose active client is $workspace. */
    protected function signInTo(Workspace $workspace, string $role = 'member'): User
    {
        $user = User::factory()->create(['role' => $role, 'current_workspace_id' => $workspace->id]);
        $this->actingAs($user);

        return $user;
    }
}

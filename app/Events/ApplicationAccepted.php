<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Application;

class ApplicationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $application;

    /**
     * Create a new event instance.
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to the project's company and the specific developer? 
        // Or maybe just a private channel for the project if we want live updates there.
        // For now, let's broadcast to the developer's private channel so they get a notification.
        return [
            new \Illuminate\Broadcasting\PrivateChannel('user.' . $this->application->developer_id),
            // new \Illuminate\Broadcasting\PrivateChannel('user.' . $this->application->project->company_id),
        ];
    }
}

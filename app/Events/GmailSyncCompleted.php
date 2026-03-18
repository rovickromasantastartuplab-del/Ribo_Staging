<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GmailSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $gmailAccountId;
    public $companyId;

    /**
     * Create a new event instance.
     */
    public function __construct($gmailAccountId, $companyId)
    {
        $this->gmailAccountId = $gmailAccountId;
        $this->companyId = $companyId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // For simplicity, we'll use a public channel for now. 
        // In a real production app, this should be a PrivateChannel('company.' . $this->companyId)
        return [
            new Channel('gmail-sync'),
        ];
    }

    /**
     * The name of the event to broadcast.
     */
    public function broadcastAs(): string
    {
        return 'gmail.sync.completed';
    }
}

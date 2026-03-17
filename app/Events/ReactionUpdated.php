<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $roomId;

    public string $roomSlug;

    public int $messageId;

    public array $reactions;

    public function __construct(
        int $roomId,
        string $roomSlug,
        int $messageId,
        array $reactions = []
    ) {
        $this->roomId = $roomId;
        $this->roomSlug = $roomSlug;
        $this->messageId = $messageId;
        $this->reactions = $reactions;
    }

    public function broadcastOn(): Channel
    {
        $channelId = $this->roomSlug ?: (string) $this->roomId;

        return new Channel('room.'.$channelId);
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'message_id' => $this->messageId,
            'reactions' => $this->reactions,
        ];
    }
}

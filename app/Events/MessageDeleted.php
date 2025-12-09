<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $messageId,
        public int $roomId,
        public ?int $deletedByUserId = null,
        public ?int $deletedByParticipantId = null,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('room.' . $this->roomId);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'room_id' => $this->roomId,
            'deleted_by_user_id' => $this->deletedByUserId,
            'deleted_by_participant_id' => $this->deletedByParticipantId,
        ];
    }
}

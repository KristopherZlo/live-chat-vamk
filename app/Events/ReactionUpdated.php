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
    public array $yourReactions;
    public ?int $actorUserId;
    public ?int $actorParticipantId;

    public function __construct(
        int $roomId,
        string $roomSlug,
        int $messageId,
        array $reactions = [],
        array $yourReactions = [],
        ?int $actorUserId = null,
        ?int $actorParticipantId = null
    ) {
        $this->roomId = $roomId;
        $this->roomSlug = $roomSlug;
        $this->messageId = $messageId;
        $this->reactions = $reactions;
        $this->yourReactions = $yourReactions;
        $this->actorUserId = $actorUserId;
        $this->actorParticipantId = $actorParticipantId;
    }

    public function broadcastOn(): Channel
    {
        $channelId = $this->roomSlug ?: (string) $this->roomId;

        return new Channel('room.' . $channelId);
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'message_id' => $this->messageId,
            'reactions' => $this->reactions,
            'your_reactions' => $this->yourReactions,
            'actor_user_id' => $this->actorUserId,
            'actor_participant_id' => $this->actorParticipantId,
        ];
    }
}

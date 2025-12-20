<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantBanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $roomId;
    public string $roomSlug;
    public int $banId;
    public int $participantId;
    public string $displayName;
    public ?string $bannedAt;
    public ?int $banCount;
    public ?int $actorUserId;
    public ?int $actorParticipantId;

    public function __construct(
        int $roomId,
        string $roomSlug,
        int $banId,
        int $participantId,
        string $displayName,
        ?string $bannedAt = null,
        ?int $banCount = null,
        ?int $actorUserId = null,
        ?int $actorParticipantId = null
    ) {
        $this->roomId = $roomId;
        $this->roomSlug = $roomSlug;
        $this->banId = $banId;
        $this->participantId = $participantId;
        $this->displayName = $displayName;
        $this->bannedAt = $bannedAt;
        $this->banCount = $banCount;
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
            'ban_id' => $this->banId,
            'participant_id' => $this->participantId,
            'display_name' => $this->displayName,
            'banned_at' => $this->bannedAt,
            'ban_count' => $this->banCount,
            'actor_user_id' => $this->actorUserId,
            'actor_participant_id' => $this->actorParticipantId,
        ];
    }
}

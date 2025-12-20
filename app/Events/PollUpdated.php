<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PollUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $roomId;
    public string $roomSlug;
    public int $messageId;
    public int $pollId;
    public array $poll;
    public ?int $yourVoteId;
    public ?int $actorUserId;
    public ?int $actorParticipantId;

    public function __construct(
        int $roomId,
        string $roomSlug,
        int $messageId,
        int $pollId,
        array $poll,
        ?int $yourVoteId = null,
        ?int $actorUserId = null,
        ?int $actorParticipantId = null
    ) {
        $this->roomId = $roomId;
        $this->roomSlug = $roomSlug;
        $this->messageId = $messageId;
        $this->pollId = $pollId;
        $this->poll = $poll;
        $this->yourVoteId = $yourVoteId;
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
            'poll_id' => $this->pollId,
            'poll' => $this->poll,
            'your_vote_id' => $this->yourVoteId,
            'actor_user_id' => $this->actorUserId,
            'actor_participant_id' => $this->actorParticipantId,
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Question;
use App\Models\QuestionRating;
use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Question $question;

    public function __construct(Question $question)
    {
        $this->question = $question->load(['participant', 'ratings', 'room']);
    }

    public function broadcastOn(): Channel
    {
        /** @var Room|null $room */
        $room = $this->question->room;
        $slug = $room?->slug;
        $channelId = $slug ?: (string) $this->question->room_id;

        return new Channel('room.' . $channelId);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->question->id,
            'room_id' => $this->question->room_id,
            'status' => $this->question->status,
            'deleted_by_owner_at' => $this->question->deleted_by_owner_at,
            'deleted_by_participant_at' => $this->question->deleted_by_participant_at,
            'ratings' => $this->question->ratings
                ->map(fn (QuestionRating $r) => [
                    'participant_id' => $r->participant_id,
                    'rating' => $r->rating,
                ])
                ->values(),
        ];
    }
}

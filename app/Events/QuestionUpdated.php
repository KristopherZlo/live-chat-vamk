<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Question $question;

    public function __construct(Question $question)
    {
        $this->question = $question->load(['participant', 'ratings']);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('room.' . $this->question->room_id);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->question->id,
            'room_id' => $this->question->room_id,
            'status' => $this->question->status,
            'deleted_by_owner_at' => $this->question->deleted_by_owner_at,
            'deleted_by_participant_at' => $this->question->deleted_by_participant_at,
            'ratings' => $this->question->ratings->map(fn ($r) => [
                'participant_id' => $r->participant_id,
                'rating' => $r->rating,
            ])->values(),
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Question $question;

    public function __construct(Question $question)
    {
        // подгружаем участника, если нужен на фронте
        $this->question = $question->load(['participant', 'room']);
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
        /** @var Participant|null $participant */
        $participant = $this->question->participant;

        return [
            'id' => $this->question->id,
            'room_id' => $this->question->room_id,
            'content' => $this->question->content,
            'status' => $this->question->status,
            'participant' => $participant?->display_name,
        ];
    }
}

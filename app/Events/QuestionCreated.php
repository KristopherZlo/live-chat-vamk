<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Question $question;

    public function __construct(Question $question)
    {
        // подгружаем участника, если нужен на фронте
        $this->question = $question->load('participant');
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
            'content' => $this->question->content,
            'status' => $this->question->status,
            'participant' => $this->question->participant?->display_name,
        ];
    }
}

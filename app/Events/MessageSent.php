<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load(['user', 'participant', 'room']);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('room.' . $this->message->room_id);
    }

    public function broadcastWith(): array
    {
        \Log::info('MessageSent broadcastWith', [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
        ]);

        return [
            'id'         => $this->message->id,
            'room_id'    => $this->message->room_id,
            'content'    => $this->message->content,
            'created_at' => $this->message->created_at->toIso8601String(),
            'author'     => [
                'type' => $this->message->user_id ? 'owner' : 'participant',
                'name' => $this->message->user_id
                    ? $this->message->user->name
                    : ($this->message->participant->display_name ?? 'Гость'),
            ],
        ];
    }
}

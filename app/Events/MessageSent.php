<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Str;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load([
            'user',
            'participant',
            'room',
            'question',
            'replyTo.user',
            'replyTo.participant',
            'reactions',
            'poll.options',
        ]);
    }

    public function broadcastOn(): Channel
    {
        $slug = $this->message->room?->slug;
        $channelId = $slug ?: (string) $this->message->room_id;

        return new Channel('room.' . $channelId);
    }

    public function broadcastWith(): array
    {
        \Log::info('MessageSent broadcastWith', [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
        ]);

        $isOwner = $this->message->user_id && $this->message->room?->user_id && $this->message->user_id === $this->message->room->user_id;

        return [
            'id'         => $this->message->id,
            'room_id'    => $this->message->room_id,
            'content'    => $this->message->content,
            'created_at' => $this->message->created_at->toIso8601String(),
            'author'     => [
                'type' => $isOwner ? 'owner' : 'participant',
                'name' => $this->message->user_id
                    ? $this->message->user->name
                    : ($this->message->participant->display_name ?? 'Guest'),
                'user_id' => $this->message->user_id,
                'participant_id' => $this->message->participant_id,
                'is_dev' => (bool) $this->message->user?->is_dev,
                'is_owner' => $isOwner,
            ],
            'as_question' => (bool) ($this->message->relationLoaded('question') ? $this->message->question : $this->message->question()->exists()),
            'reply_to' => $this->message->replyTo ? [
                'id' => $this->message->replyTo->id,
                'author' => $this->message->replyTo->user_id
                    ? $this->message->replyTo->user?->name
                    : ($this->message->replyTo->participant?->display_name ?? 'Guest'),
                'content' => $this->message->replyTo->trashed()
                    ? 'Message deleted'
                    : Str::limit($this->message->replyTo->content, 140),
                'is_deleted' => $this->message->replyTo->trashed(),
            ] : null,
            'reactions' => $this->formatReactions(),
            'poll' => $this->formatPollPayload(),
        ];
    }

    protected function formatReactions(): array
    {
        $grouped = $this->message->reactions->groupBy('emoji');

        return $grouped
            ->map(fn ($items, $emoji) => [
                'emoji' => $emoji,
                'count' => $items->count(),
            ])
            ->values()
            ->toArray();
    }

    protected function formatPollPayload(): ?array
    {
        $poll = $this->message->poll;
        if (!$poll) {
            return null;
        }

        $poll->loadMissing('options');

        $options = $poll->options
            ->sortBy('position')
            ->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'votes' => 0,
                'percent' => 0,
            ])
            ->values()
            ->toArray();

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'options' => $options,
            'total_votes' => 0,
            'my_vote_id' => null,
            'is_closed' => (bool) $poll->is_closed,
        ];
    }
}

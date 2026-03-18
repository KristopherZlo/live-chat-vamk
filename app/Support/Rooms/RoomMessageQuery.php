<?php

namespace App\Support\Rooms;

use App\Models\Participant;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class RoomMessageQuery
{
    public static function forRoom(Room $room): Relation
    {
        return $room->messages()
            ->with([
                'participant:id,display_name',
                'user:id,name,is_dev',
                'replyTo' => fn ($query) => $query->select('id', 'user_id', 'participant_id', 'content', 'deleted_at'),
                'replyTo.user:id,name',
                'replyTo.participant:id,display_name',
            ])
            ->withExists(['question as has_question'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public static function excludeBannedParticipants(Builder|Relation $query, Room $room, ?Participant $participant = null): Builder|Relation
    {
        $bannedIds = $room->bans()
            ->pluck('participant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($bannedIds->isEmpty()) {
            return $query;
        }

        if ($participant && $participant->id) {
            $viewerId = (int) $participant->id;
            $bannedIds = $bannedIds->reject(fn ($id) => $id === $viewerId)->values();
        }

        if ($bannedIds->isEmpty()) {
            return $query;
        }

        return $query->where(function ($nestedQuery) use ($bannedIds) {
            $nestedQuery->whereNull('participant_id')
                ->orWhereNotIn('participant_id', $bannedIds);
        });
    }
}

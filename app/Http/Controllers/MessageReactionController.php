<?php

namespace App\Http\Controllers;

use App\Events\ReactionUpdated;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageReactionController extends Controller
{
    public function toggle(Request $request, Room $room, Message $message)
    {
        if ($message->room_id !== $room->id) {
            abort(404);
        }

        if ($room->status !== 'active') {
            return response()->json(['message' => 'Room is closed for reactions.'], 403);
        }

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        $user = Auth::user();
        $isOwner = $user && $user->id === $room->user_id;
        $isDevUser = $user && !$isOwner && $user->is_dev;

        $participant = null;

        if (!$isOwner && !$isDevUser) {
            $sessionKey = 'room_participant_' . $room->id;
            $participantId = $request->session()->get($sessionKey);
            if ($participantId) {
                $participant = Participant::find($participantId);
            }

            if (!$participant) {
                return response()->json(['message' => 'Session expired. Please refresh and try again.'], 403);
            }

            $fingerprint = $request->cookie('lc_fp');
            if ($room->isParticipantBanned($participant, $request->ip(), $fingerprint)) {
                return response()->json(['message' => 'You are banned from reacting in this room.'], 403);
            }
        }

        $actorUserId = $isOwner || $isDevUser ? $user?->id : null;
        $actorParticipantId = $participant?->id;

        if (!$actorUserId && !$actorParticipantId) {
            return response()->json(['message' => 'Unknown participant.'], 403);
        }

        $emoji = $data['emoji'];
        $action = 'added';
        $yourReactions = [];

        DB::transaction(function () use ($message, $emoji, $actorUserId, $actorParticipantId, &$action, &$yourReactions) {
            $criteria = [
                'message_id' => $message->id,
                'emoji' => $emoji,
            ];

            if ($actorUserId) {
                $criteria['user_id'] = $actorUserId;
            } else {
                $criteria['participant_id'] = $actorParticipantId;
            }

            $existing = MessageReaction::where($criteria)->lockForUpdate()->first();

            if ($existing) {
                $existing->delete();
                $action = 'removed';
            } else {
                MessageReaction::create([
                    'message_id' => $message->id,
                    'emoji' => $emoji,
                    'user_id' => $actorUserId,
                    'participant_id' => $actorParticipantId,
                ]);
            }

            $yourReactions = MessageReaction::where('message_id', $message->id)
                ->where(function ($query) use ($actorUserId, $actorParticipantId) {
                    if ($actorUserId) {
                        $query->where('user_id', $actorUserId);
                    } else {
                        $query->where('participant_id', $actorParticipantId);
                    }
                })
                ->pluck('emoji')
                ->unique()
                ->values()
                ->toArray();
        });

        $summary = $this->summarizeReactions($message->id);

        try {
            event(new ReactionUpdated(
                $room->id,
                $message->id,
                $summary,
                $yourReactions,
                $actorUserId,
                $actorParticipantId
            ));
        } catch (\Throwable $e) {
            Log::warning('Reaction broadcast failed', [
                'room_id' => $room->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => $action,
            'reactions' => $summary,
            'your_reactions' => $yourReactions,
        ]);
    }

    protected function summarizeReactions(int $messageId): array
    {
        return MessageReaction::select('emoji', DB::raw('count(*) as count'))
            ->where('message_id', $messageId)
            ->groupBy('emoji')
            ->orderByDesc('count')
            ->orderBy('emoji')
            ->get()
            ->map(fn ($row) => [
                'emoji' => $row->emoji,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();
    }
}

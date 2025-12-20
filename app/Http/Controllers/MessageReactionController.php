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

        $emoji = trim($data['emoji']);
        if (!$this->isValidEmoji($emoji)) {
            return response()->json(['message' => 'Invalid reaction emoji.'], 422);
        }

        $action = 'added';
        $yourReactions = [];

        DB::transaction(function () use ($message, $emoji, $actorUserId, $actorParticipantId, &$action, &$yourReactions) {
            $actorScope = function ($query) use ($actorUserId, $actorParticipantId) {
                if ($actorUserId) {
                    $query->where('user_id', $actorUserId);
                } else {
                    $query->where('participant_id', $actorParticipantId);
                }
            };

            $existing = MessageReaction::where('message_id', $message->id)
                ->where($actorScope)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->emoji === $emoji) {
                    $existing->delete();
                    $action = 'removed';
                    $yourReactions = [];
                    return;
                }

                $existing->emoji = $emoji;
                $existing->save();
                $action = 'updated';
                $yourReactions = [$emoji];
                return;
            }

            MessageReaction::create([
                'message_id' => $message->id,
                'emoji' => $emoji,
                'user_id' => $actorUserId,
                'participant_id' => $actorParticipantId,
            ]);
            $action = 'added';
            $yourReactions = [$emoji];
        });

        $summary = $this->summarizeReactions($message->id);

        try {
            event(new ReactionUpdated(
                $room->id,
                $room->slug,
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

    protected function isValidEmoji(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // Reject plain text/letters/numbers
        if (preg_match('/[\\p{L}\\p{N}]/u', $value)) {
            return false;
        }

        // Require at least one pictographic/emoji-like codepoint
        return (bool) preg_match('/[\\x{1F300}-\\x{1FAFF}\\x{1F1E6}-\\x{1F1FF}\\x{2600}-\\x{27BF}]/u', $value);
    }

    protected function summarizeReactions(int $messageId): array
    {
        $rows = MessageReaction::query()
            ->select('emoji')
            ->where('message_id', $messageId)
            ->get();

        return $rows
            ->groupBy('emoji')
            ->map(fn ($items, $emoji) => [
                'emoji' => $emoji,
                'count' => $items->count(),
            ])
            ->sort(function ($a, $b) {
                $countDiff = $b['count'] <=> $a['count'];
                if ($countDiff !== 0) {
                    return $countDiff;
                }
                return strcasecmp($a['emoji'], $b['emoji']);
            })
            ->values()
            ->toArray();
    }
}

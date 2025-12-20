<?php

namespace App\Http\Controllers;

use App\Events\PollUpdated;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\Participant;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessagePollController extends Controller
{
    public function vote(Request $request, Room $room, MessagePoll $poll)
    {
        $poll->loadMissing(['message', 'options']);

        if (!$poll->message || $poll->message->room_id !== $room->id) {
            abort(404);
        }

        if ($room->status !== 'active') {
            return response()->json(['message' => 'Room is closed for polls.'], 403);
        }
        if ($poll->is_closed) {
            return response()->json(['message' => 'This poll is closed.'], 403);
        }

        $data = $request->validate([
            'option_id' => [
                'required',
                'integer',
                Rule::exists('message_poll_options', 'id')->where('poll_id', $poll->id),
            ],
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
                return response()->json(['message' => 'You are banned from voting in this poll.'], 403);
            }
        }

        $actorUserId = $isOwner || $isDevUser ? $user?->id : null;
        $actorParticipantId = $participant?->id;

        if (!$actorUserId && !$actorParticipantId) {
            return response()->json(['message' => 'Unknown participant.'], 403);
        }

        $optionId = (int) $data['option_id'];
        $selectedOptionId = $optionId;

        DB::transaction(function () use ($poll, $optionId, $actorUserId, $actorParticipantId, &$selectedOptionId) {
            $actorScope = function ($query) use ($actorUserId, $actorParticipantId) {
                if ($actorUserId) {
                    $query->where('user_id', $actorUserId);
                } else {
                    $query->where('participant_id', $actorParticipantId);
                }
            };

            $existing = MessagePollVote::where('poll_id', $poll->id)
                ->where($actorScope)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->option_id = $optionId;
                $existing->save();
                return;
            }

            MessagePollVote::create([
                'poll_id' => $poll->id,
                'option_id' => $optionId,
                'user_id' => $actorUserId,
                'participant_id' => $actorParticipantId,
            ]);
        });

        $pollPayload = $this->buildPollPayload($poll);

        try {
            event(new PollUpdated(
                $room->id,
                $room->slug,
                $poll->message_id,
                $poll->id,
                $pollPayload,
                $selectedOptionId,
                $actorUserId,
                $actorParticipantId
            ));
        } catch (\Throwable $e) {
            // Broadcast failures should not block poll voting.
        }

        return response()->json([
            'status' => 'voted',
            'poll_id' => $poll->id,
            'message_id' => $poll->message_id,
            'poll' => $pollPayload,
            'your_vote_id' => $selectedOptionId,
        ]);
    }

    protected function buildPollPayload(MessagePoll $poll): array
    {
        $poll->loadMissing('options');

        $votes = MessagePollVote::query()
            ->select('option_id')
            ->where('poll_id', $poll->id)
            ->get();

        $counts = $votes->groupBy('option_id')->map->count();
        $totalVotes = $counts->sum();

        $options = $poll->options
            ->sortBy('position')
            ->map(function ($option) use ($counts, $totalVotes) {
                $votesCount = (int) ($counts->get($option->id, 0));
                $percent = $totalVotes > 0 ? (int) round(($votesCount / $totalVotes) * 100) : 0;
                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => $votesCount,
                    'percent' => $percent,
                ];
            })
            ->values()
            ->toArray();

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'options' => $options,
            'total_votes' => $totalVotes,
            'my_vote_id' => null,
            'is_closed' => (bool) $poll->is_closed,
        ];
    }
}

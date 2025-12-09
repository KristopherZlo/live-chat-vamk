<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\MessageSent;
use App\Events\QuestionCreated;
use App\Events\MessageDeleted;
use App\Events\QuestionUpdated;

class MessageController extends Controller
{
    public function store(Request $request, Room $room)
    {
        $user = Auth::user();
        $isOwner = $user && $user->id === $room->user_id;
        $isDevUser = $user && !$isOwner && $user->is_dev;
        $ipAddress = $request->ip();
        $fingerprint = $request->cookie('lc_fp');

        if ($room->status !== 'active') {
            $message = 'Room is closed for new messages.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()
                ->route('rooms.public', $room->slug)
                ->withErrors($message);
        }

        // Basic validation
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'as_question' => ['nullable', 'boolean'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        // Identify participant (if not the owner)
        $participant = null;

        if ($isOwner) {
            $participant = null;
        } else {
            $sessionKey = 'room_participant_' . $room->id;
            $participantId = $request->session()->get($sessionKey);

            if ($participantId) {
                $participant = Participant::find($participantId);
                if ($participant && $user && $user->is_dev && $participant->display_name !== $user->name) {
                    $participant->display_name = $user->name;
                    $participant->save();
                }
                if (!$participant) {
                    $request->session()->forget($sessionKey);
                }
            }

            if (!$participant) {
                return back()->withErrors('Session expired. Please refresh and try again.');
            }
        }

        if ($participant && $room->isParticipantBanned($participant, $ipAddress, $fingerprint)) {
            $message = 'You are banned from sending messages in this room.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors($message);
        }

        $replyMessage = null;
        if (!empty($data['reply_to_id'])) {
            $replyMessage = Message::where('room_id', $room->id)->find($data['reply_to_id']);
            if (!$replyMessage) {
                return back()->withErrors('Reply target not found in this room.');
            }
        }

        $messageUserId = $isOwner || $isDevUser ? $user->id : null;

        $message = Message::create([
            'room_id' => $room->id,
            'participant_id' => $participant?->id,
            'reply_to_id' => $replyMessage?->id,
            'user_id' => $messageUserId,
            'is_system' => false,
            'content' => $data['content'],
        ]);

        $question = null;

        if (!empty($data['as_question'])) {
            $question = Question::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'participant_id' => $participant?->id,
                'user_id' => $messageUserId,
                'content' => $data['content'],
                'status' => 'new',
            ]);

            // Keep the relation in memory so broadcast metadata knows this was sent to host
            $message->setRelation('question', $question);
            if ($replyMessage) {
                $message->setRelation('replyTo', $replyMessage);
            }

            event(new QuestionCreated($question));
        }

        if ($replyMessage && !$message->relationLoaded('replyTo')) {
            $message->setRelation('replyTo', $replyMessage);
        }

        event(new MessageSent($message));

        if ($request->expectsJson()) {
            return response()->json([
                'message_id' => $message->id,
                'question_id' => $question?->id,
                'as_question' => (bool) $question,
            ], 201);
        }

        return redirect()
            ->route('rooms.public', $room->slug)
            ->with('status', 'Message sent.');
    }

    public function destroy(Request $request, Room $room, Message $message)
    {
        if ($message->room_id !== $room->id) {
            abort(404);
        }

        $user = Auth::user();
        $isOwner = $user && $user->id === $room->user_id;

        $participant = null;
        $sessionKey = 'room_participant_' . $room->id;

        if (!$isOwner) {
            $participantId = $request->session()->get($sessionKey);
            if ($participantId) {
                $participant = Participant::find($participantId);
            }
        }

        $isAuthor = false;

        if ($user && $message->user_id && $message->user_id === $user->id) {
            $isAuthor = true;
        } elseif ($participant && $message->participant_id && $message->participant_id === $participant->id) {
            $isAuthor = true;
        }

        if (!$isOwner && !$isAuthor) {
            $response = ['message' => 'You cannot delete this message.'];
            return $request->expectsJson()
                ? response()->json($response, 403)
                : abort(403, $response['message']);
        }

        $message->loadMissing('question');

        if ($message->trashed()) {
            return $request->expectsJson()
                ? response()->json(['status' => 'deleted'])
                : back()->with('status', 'Message removed.');
        }

        $deletedByUserId = $isOwner || ($user && $message->user_id === $user->id)
            ? $user?->id
            : null;
        $deletedByParticipantId = !$deletedByUserId && $participant && $message->participant_id === $participant->id
            ? $participant->id
            : null;

        DB::transaction(function () use ($message, $deletedByUserId, $deletedByParticipantId, $isOwner) {
            $message->deleted_by_user_id = $deletedByUserId;
            $message->deleted_by_participant_id = $deletedByParticipantId;
            $message->save();
            $message->delete();

            if ($message->question) {
                if ($isOwner) {
                    $message->question->deleted_by_owner_at = now();
                } elseif ($deletedByParticipantId) {
                    $message->question->deleted_by_participant_at = now();
                }
                $message->question->save();
                event(new QuestionUpdated($message->question));
            }
        });

        event(new MessageDeleted(
            $message->id,
            $room->id,
            $deletedByUserId,
            $deletedByParticipantId
        ));

        if ($request->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back()->with('status', 'Message removed.');
    }
}

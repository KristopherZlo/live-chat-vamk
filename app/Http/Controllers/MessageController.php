<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MessageSent;
use App\Events\QuestionCreated;

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

        return redirect()->route('rooms.public', $room->slug);
    }
}

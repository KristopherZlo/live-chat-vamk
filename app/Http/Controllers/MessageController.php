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

        // базовая валидация
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'as_question' => ['nullable', 'boolean'],
        ]);

        // определяем участника
        $participant = null;

        if ($user && $user->id === $room->user_id) {
            // владелец комнаты — сообщения от имени владельца
            $participant = null;
        } else {
            $sessionKey = 'room_participant_' . $room->id;
            $participantId = $request->session()->get($sessionKey);

            if ($participantId) {
                $participant = Participant::find($participantId);
                if (!$participant) {
                    $request->session()->forget($sessionKey);
                }
            }

            if (!$participant) {
                return back()->withErrors('Ошибка участника. Обнови страницу.');
            }
        }

        // создаём сообщение
        $message = Message::create([
            'room_id' => $room->id,
            'participant_id' => $participant?->id,
            'user_id' => $user && $user->id === $room->user_id ? $user->id : null,
            'is_system' => false,
            'content' => $data['content'],
        ]);

        event(new MessageSent($message));

        // если стоит галочка "как вопрос создателю"
        if (!empty($data['as_question'])) {
            $question = Question::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'participant_id' => $participant?->id,
                'user_id' => null,
                'content' => $data['content'],
                'status' => 'new',
            ]);

            event(new QuestionCreated($question));
        }

        return redirect()->route('rooms.public', $room->slug);
    }
}

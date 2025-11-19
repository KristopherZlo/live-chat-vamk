<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MessageSent;

class MessageController extends Controller
{
    public function store(Request $request, Room $room)
    {
        // нельзя писать в завершённый чат
        if ($room->status === 'finished') {
            return back()->withErrors('Чат завершён, новые сообщения нельзя отправить.');
        }

        $data = $request->validate([
            'content' => ['required', 'string'],
            'as_question' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();

        $participant = null;
        if (!$user || $user->id !== $room->user_id) {
            // ищем participant из сессии
            $sessionKey = 'room_participant_' . $room->id;
            $participantId = $request->session()->get($sessionKey);

            if ($participantId) {
                $participant = Participant::find($participantId);
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
            Question::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'participant_id' => $participant?->id,
                'user_id' => null,
                'content' => $data['content'],
                'status' => 'new',
            ]);
        }

        return redirect()->route('rooms.public', $room->slug);
    }
}

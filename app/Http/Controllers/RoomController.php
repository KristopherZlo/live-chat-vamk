<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    // Список комнат владельца
    public function dashboard(Request $request)
    {
        $rooms = $request->user()
            ->rooms()
            ->latest()
            ->get();

        return view('dashboard', compact('rooms'));
    }

    // Форма создания комнаты
    public function create()
    {
        return view('rooms.create');
    }

    // Создание комнаты
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public_read' => ['nullable', 'boolean'],
        ]);

        $room = Room::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'slug' => Str::random(10),
            'is_public_read' => $data['is_public_read'] ?? true,
        ]);

        return redirect()
            ->route('rooms.public', $room->slug)
            ->with('status', 'Комната создана');
    }

    // Публичная страница комнаты /r/{slug}
    public function showPublic(Request $request, $slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if ($room->status === 'finished' && !$room->is_public_read) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room);

        $messages = $room->messages()
            ->with(['participant', 'user'])
            ->orderBy('created_at')
            ->get();

        $isOwner = $request->user() && $room->user_id === $request->user()->id;

        $queueQuestions = collect();
        $historyQuestions = collect();
        $myQuestions = collect();

        if ($isOwner) {
            // 1) Очередь: только активные new/later, не скрытые и не удалённые участником
            $queueQuestions = $room->questions()
                ->with('participant')
                ->whereIn('status', ['new', 'later'])
                ->whereNull('deleted_by_owner_at')
                ->whereNull('deleted_by_participant_at')
                ->orderBy('created_at')
                ->get();

            // 2) История:
            //    - не удалён участником
            //    - либо статус НЕ new/later (answered/ignored),
            //    - либо скрыто из очереди владельцем (deleted_by_owner_at not null)
            $historyQuestions = $room->questions()
                ->with(['participant', 'ratings'])
                ->whereNull('deleted_by_participant_at')
                ->where(function ($q) {
                    $q->whereNotIn('status', ['new', 'later'])
                    ->orWhereNotNull('deleted_by_owner_at');
                })
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // мои вопросы для текущего участника
            if ($participant && $participant->id) {
                $myQuestions = $room->questions()
                    ->where('participant_id', $participant->id)
                    ->whereNull('deleted_by_participant_at')
                    ->with(['ratings' => function ($query) use ($participant) {
                        $query->where('participant_id', $participant->id);
                    }])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        return view('rooms.show', [
            'room' => $room,
            'messages' => $messages,
            'participant' => $participant,
            'isOwner' => $isOwner,
            'queueQuestions' => $queueQuestions,
            'historyQuestions' => $historyQuestions,
            'myQuestions' => $myQuestions,
        ]);
    }

    protected function getOrCreateParticipant(Request $request, Room $room): Participant
    {
        // если пользователь — владелец комнаты, участник не нужен
        if ($request->user() && $request->user()->id === $room->user_id) {
            return new Participant([
                'room_id' => $room->id,
                'session_token' => '',
                'display_name' => $request->user()->name,
            ]);
        }

        $sessionKey = 'room_participant_' . $room->id;

        $participantId = $request->session()->get($sessionKey);

        if ($participantId) {
            $participant = Participant::find($participantId);
            if ($participant) {
                return $participant;
            }
        }

        // создаём нового участника
        $token = Str::uuid()->toString();

        $participant = Participant::create([
            'room_id' => $room->id,
            'session_token' => $token,
            'display_name' => 'User' . random_int(1000, 9999),
        ]);

        $request->session()->put($sessionKey, $participant->id);

        return $participant;
    }

    public function questionsPanel(Room $room)
    {
        $user = auth()->user();
        $isOwner = $user && $user->id === $room->user_id;

        if (! $isOwner) {
            abort(403);
        }

        // та же логика, что ты используешь в showPublic для правой панели
        $queueQuestions = $room->questions()
            ->whereIn('status', ['new', 'later'])
            ->whereNull('deleted_by_owner_at')
            ->whereNull('deleted_by_participant_at')
            ->orderBy('created_at')
            ->get();

        $historyQuestions = $room->questions()
            ->whereNull('deleted_by_participant_at')
            ->where(function ($q) {
                $q->whereNotIn('status', ['new', 'later'])
                ->orWhereNotNull('deleted_by_owner_at');
            })
            ->orderByDesc('created_at')
            ->get();

        return view('rooms.partials.questions_panel', [
            'room'            => $room,
            'queueQuestions'  => $queueQuestions,
            'historyQuestions'=> $historyQuestions,
            'isOwner'         => $isOwner,
        ]);
    }
}

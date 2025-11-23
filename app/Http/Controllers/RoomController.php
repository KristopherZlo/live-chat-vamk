<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    protected function ensureOwner(Room $room): void
    {
        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }
    }

    // Список комнат владельца
    public function dashboard(Request $request)
    {
        $rooms = $request->user()
            ->rooms()
            ->withCount(['messages', 'questions'])
            ->latest('updated_at')
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

    // Update room details and status
    public function update(Request $request, Room $room)
    {
        $this->ensureOwner($room);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:active,finished'],
        ]);

        $changes = [];

        if (array_key_exists('title', $data)) {
            $changes['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $changes['description'] = $data['description'] ?? null;
        }

        if (array_key_exists('status', $data) && $data['status'] !== $room->status) {
            $changes['status'] = $data['status'];
            $changes['finished_at'] = $data['status'] === 'finished' ? now() : null;
        }

        if (empty($changes)) {
            return back()->with('status', 'No changes applied.');
        }

        $room->update($changes);

        $statusMessage = 'Room updated.';

        if (array_key_exists('status', $changes)) {
            $statusMessage = $changes['status'] === 'finished'
                ? 'Room closed for participants.'
                : 'Room reopened for participants.';
        }

        return back()->with('status', $statusMessage);
    }

    public function destroy(Request $request, Room $room)
    {
        $this->ensureOwner($room);

        $request->validate([
            'confirm_title' => ['required', 'string', Rule::in([$room->title])],
        ], [
            'confirm_title.in' => 'The room name does not match. Type it exactly to delete.',
        ]);

        $room->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Room deleted along with its questions and messages.');
    }

    public function showPublic(Request $request, $slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if ($room->status === 'finished' && !$room->is_public_read) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room);

        $messages = $room->messages()
            ->with(['participant', 'user', 'question', 'replyTo.user', 'replyTo.participant'])
            ->orderBy('created_at')
            ->get();

        $isOwner = $request->user() && $room->user_id === $request->user()->id;

        $queueQuestions = collect();
        $historyQuestions = collect();

        if ($isOwner) {
            $queueQuestions = $room->questions()
                ->with('participant')
                ->whereIn('status', ['new', 'later'])
                ->whereNull('deleted_by_owner_at')
                ->whereNull('deleted_by_participant_at')
                ->orderBy('created_at')
                ->get();

            $historyQuestions = $room->questions()
                ->with(['participant', 'ratings'])
                ->whereNull('deleted_by_participant_at')
                ->where(function ($q) {
                    $q->whereNotIn('status', ['new', 'later'])
                    ->orWhereNotNull('deleted_by_owner_at');
                })
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $myQuestions = collect();

        if (! $isOwner && $participant && $participant->id) {
            $myQuestions = $room->questions()
                ->where('participant_id', $participant->id)
                ->whereNull('deleted_by_participant_at')
                ->with(['ratings' => function ($query) use ($participant) {
                    $query->where('participant_id', $participant->id);
                }])
                ->orderBy('created_at', 'desc')
                ->get();
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
        $user = $request->user();

        // если пользователь — владелец комнаты, участник не нужен
        if ($user && $user->id === $room->user_id) {
            return new Participant([
                'room_id' => $room->id,
                'session_token' => '',
                'display_name' => $user->name,
            ]);
        }

        $sessionKey = 'room_participant_' . $room->id;

        $participantId = $request->session()->get($sessionKey);

        if ($participantId) {
            $participant = Participant::find($participantId);
            if ($participant) {
                if ($user && $user->is_dev && $participant->display_name !== $user->name) {
                    $participant->display_name = $user->name;
                    $participant->save();
                }

                return $participant;
            }
        }

        // создаём нового участника
        $token = Str::uuid()->toString();

        $participant = Participant::create([
            'room_id' => $room->id,
            'session_token' => $token,
            'display_name' => $user && $user->is_dev ? $user->name : 'User' . random_int(1000, 9999),
        ]);

        $request->session()->put($sessionKey, $participant->id);

        return $participant;
    }

    public function questionsPanel(Room $room)
    {
        $user = auth()->user();
        $isOwner = $user && $user->id === $room->user_id;

                // та же логика, что ты используешь в showPublic для правой панели
        $queueQuestions = $room->questions()
            ->with('participant')
            ->whereIn('status', ['new', 'later'])
            ->whereNull('deleted_by_owner_at')
            ->whereNull('deleted_by_participant_at')
            ->orderBy('created_at')
            ->get();

        $historyQuestions = $room->questions()
            ->with(['participant', 'ratings'])
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

    public function myQuestionsPanel(Request $request, Room $room)
    {
        if ($request->user() && $request->user()->id === $room->user_id) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room);

        if (!$participant || !$participant->id) {
            abort(403);
        }

        $myQuestions = $room->questions()
            ->where('participant_id', $participant->id)
            ->whereNull('deleted_by_participant_at')
            ->with(['ratings' => function ($query) use ($participant) {
                $query->where('participant_id', $participant->id);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('rooms.partials.my_questions_panel', [
            'room' => $room,
            'myQuestions' => $myQuestions,
        ]);
    }
}

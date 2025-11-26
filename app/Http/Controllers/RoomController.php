<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use App\Models\Participant;
use App\Models\RoomBan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function landing()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('rooms.join');
    }

    public function joinForm()
    {
        return view('rooms.join');
    }

    public function joinSubmit(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $input = trim($data['code']);

        $slug = Str::of($input)
            ->afterLast('/')
            ->before('?')
            ->before('#')
            ->trim()
            ->value();

        if ($slug === '') {
            return back()
                ->withErrors(['code' => 'Enter a valid room code.'])
                ->withInput();
        }

        $room = Room::where('slug', $slug)->first();

        if (!$room) {
            return back()
                ->withErrors(['code' => 'Room not found. Check the code and try again.'])
                ->withInput();
        }

        return redirect()->route('rooms.public', $slug);
    }

    protected function ensureOwner(Room $room): void
    {
        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }
    }

    public function dashboard(Request $request)
    {
        $rooms = $request->user()
            ->rooms()
            ->withCount(['messages', 'questions'])
            ->latest('updated_at')
            ->get();

        return view('dashboard', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

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
            ->with('status', 'Room created.');
    }

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

        $fingerprint = $this->resolveFingerprint($request);
        $ipAddress = $request->ip();

        if (!$this->isOwner($room) && $this->isIdentityBanned($room, $ipAddress, $fingerprint)) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room, $fingerprint, $ipAddress);

        $messages = $room->messages()
            ->with(['participant', 'user', 'question', 'replyTo.user', 'replyTo.participant'])
            ->orderBy('created_at')
            ->get();

        $isOwner = $this->isOwner($room);
        $isBanned = false;

        $queueQuestions = collect();
        $historyQuestions = collect();
        $bannedParticipants = collect();

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

            $bannedParticipants = $room->bans()
                ->with('participant')
                ->orderByDesc('created_at')
                ->get();
        } elseif ($participant && $participant->id) {
            $isBanned = $room->isParticipantBanned($participant, $ipAddress, $fingerprint);
        }

        $myQuestions = collect();

        if (!$isOwner && $participant && $participant->id) {
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
            'isBanned' => $isBanned,
            'bannedParticipants' => $bannedParticipants,
            'queueQuestions' => $queueQuestions,
            'historyQuestions' => $historyQuestions,
            'myQuestions' => $myQuestions,
        ]);
    }

    protected function getOrCreateParticipant(Request $request, Room $room, string $fingerprint, ?string $ipAddress = null): Participant
    {
        $user = $request->user();
        $ipAddress = $ipAddress ?? $request->ip();
        $hasIdentityColumns = Schema::hasColumn('participants', 'ip_address')
            && Schema::hasColumn('participants', 'fingerprint');

        //      
        if ($user && $user->id === $room->user_id) {
            $data = [
                'room_id' => $room->id,
                'session_token' => '',
                'display_name' => $user->name,
            ];

            if ($hasIdentityColumns) {
                $data['ip_address'] = $ipAddress;
                $data['fingerprint'] = $fingerprint;
            }

            return new Participant($data);
        }

        $sessionKey = 'room_participant_' . $room->id;

        $participantId = $request->session()->get($sessionKey);

        if ($participantId) {
            $participant = Participant::find($participantId);
            if ($participant) {
                if ($user && $user->is_dev && $participant->display_name !== $user->name) {
                    $participant->display_name = $user->name;
                }

                if ($hasIdentityColumns && ($participant->ip_address !== $ipAddress || $participant->fingerprint !== $fingerprint)) {
                    $participant->ip_address = $ipAddress;
                    $participant->fingerprint = $fingerprint;
                }

                if ($participant->isDirty()) {
                    $participant->save();
                }

                return $participant;
            }
        }

        $token = Str::uuid()->toString();

        $participantData = [
            'room_id' => $room->id,
            'session_token' => $token,
            'display_name' => $user && $user->is_dev ? $user->name : 'User' . random_int(1000, 9999),
        ];

        if ($hasIdentityColumns) {
            $participantData['ip_address'] = $ipAddress;
            $participantData['fingerprint'] = $fingerprint;
        }

        $participant = Participant::create($participantData);

        $request->session()->put($sessionKey, $participant->id);

        return $participant;
    }

    public function questionsPanel(Room $room)
    {
        $user = auth()->user();
        $isOwner = $user && $user->id === $room->user_id;

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

        $participant = $this->getOrCreateParticipant($request, $room, $this->resolveFingerprint($request), $request->ip());

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

    protected function resolveFingerprint(Request $request): string
    {
        $existing = $request->cookie('lc_fp');
        if ($existing) {
            return $existing;
        }

        $fingerprint = (string) Str::uuid();
        Cookie::queue(cookie('lc_fp', $fingerprint, 60 * 24 * 365, '/', null, false, false));

        return $fingerprint;
    }

    protected function isIdentityBanned(Room $room, ?string $ipAddress, ?string $fingerprint): bool
    {
        $hasIdentityColumns = Schema::hasColumn('room_bans', 'ip_address')
            && Schema::hasColumn('room_bans', 'fingerprint');

        if (!$hasIdentityColumns) {
            return false;
        }

        if (!$ipAddress && !$fingerprint) {
            return false;
        }

        return $room->bans()
            ->where(function ($query) use ($ipAddress, $fingerprint) {
                if ($ipAddress) {
                    $query->orWhere('ip_address', $ipAddress);
                }
                if ($fingerprint) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->exists();
    }

    protected function isOwner(Room $room): bool
    {
        return auth()->check() && auth()->id() === $room->user_id;
    }
}

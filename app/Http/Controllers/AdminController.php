<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'dev']);
    }

    public function index()
    {
        $stats = [
            'users' => User::count(),
            'rooms' => Room::count(),
            'messages' => Message::count(),
            'questions' => Question::count(),
            'participants' => Participant::count(),
            'active_users' => $this->countActiveUsers(),
        ];

        $inviteCodes = InviteCode::with('usedBy')
            ->latest()
            ->get();

        $rooms = Room::with('owner')
            ->withCount(['messages', 'questions', 'bans'])
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'rooms_page');

        $topRooms = Room::with('owner')
            ->withCount(['messages', 'questions'])
            ->orderByDesc('messages_count')
            ->limit(6)
            ->get();

        $recentUsers = User::orderByDesc('created_at')
            ->paginate(20, ['*'], 'users_page');

        $recentUsedInvites = InviteCode::with('usedBy')
            ->whereNotNull('used_at')
            ->latest('used_at')
            ->limit(8)
            ->get();

        $allRooms = Room::orderBy('title')->get(['id', 'title', 'slug']);

        $recentBans = RoomBan::with(['room', 'participant'])
            ->latest()
            ->limit(20)
            ->get();

        $participants = Participant::with('room')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'participants_page');

        return view('admin.index', compact(
            'stats',
            'inviteCodes',
            'recentUsedInvites',
            'rooms',
            'topRooms',
            'recentUsers',
            'recentBans',
            'allRooms',
            'participants'
        ));
    }

    public function storeInvite(Request $request)
    {
        $request->validate([
            'code' => ['nullable', 'string', 'max:64', 'unique:invite_codes,code'],
        ]);

        $code = $request->input('code') ?: Str::upper(Str::random(12));

        InviteCode::create([
            'code' => $code,
        ]);

        return redirect()->route('admin.index')->with('status', 'Invite code created: '.$code);
    }

    public function destroyInvite(InviteCode $invite)
    {
        $invite->delete();

        return redirect()->route('admin.index')->with('status', 'Invite code removed.');
    }

    public function storeBan(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'participant_id' => ['nullable', 'exists:participants,id'],
            'session_token' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        RoomBan::create($data);

        return redirect()->route('admin.index')->with('status', 'Ban added.');
    }

    public function destroyBan(RoomBan $ban)
    {
        $ban->delete();

        return redirect()->route('admin.index')->with('status', 'Ban removed.');
    }

    protected function countActiveUsers(): int
    {
        try {
            return DB::table('sessions')
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

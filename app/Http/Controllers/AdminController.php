<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\Setting;
use App\Models\UpdatePost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $stats = Cache::remember('admin:stats', 60, function () {
            return [
                'users' => User::count(),
                'rooms' => Room::count(),
                'messages' => Message::count(),
                'questions' => Question::count(),
                'participants' => Participant::count(),
                'active_users' => $this->countActiveUsers(),
            ];
        });

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

        $allRooms = Cache::remember('admin:all_rooms_list', 60, function () {
            return Room::orderBy('title')->get(['id', 'title', 'slug']);
        });

        $recentBans = RoomBan::with(['room', 'participant'])
            ->latest()
            ->limit(20)
            ->get();

        $participants = Participant::with('room')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'participants_page');

        $health = $this->resolveHealthStatus();
        $appVersion = Setting::getValue('app_version', config('app.version', '1.0.0'));

        $blogUpdates = UpdatePost::query()
            ->where('type', UpdatePost::TYPE_BLOG)
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'updates_page');

        $whatsNewEntries = UpdatePost::query()
            ->where('type', UpdatePost::TYPE_WHATS_NEW)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'whatsnew_page');

        $editingBlog = null;
        $editingRelease = null;

        $editPostId = request()->query('edit_post');
        if ($editPostId) {
            $editingBlog = UpdatePost::query()
                ->where('id', $editPostId)
                ->where('type', UpdatePost::TYPE_BLOG)
                ->first();
        }

        $editReleaseId = request()->query('edit_release');
        if ($editReleaseId) {
            $editingRelease = UpdatePost::query()
                ->where('id', $editReleaseId)
                ->where('type', UpdatePost::TYPE_WHATS_NEW)
                ->first();
        }

        return view('admin.index', compact(
            'stats',
            'inviteCodes',
            'recentUsedInvites',
            'rooms',
            'topRooms',
            'recentUsers',
            'recentBans',
            'allRooms',
            'participants',
            'health',
            'blogUpdates',
            'whatsNewEntries',
            'editingBlog',
            'editingRelease',
            'appVersion'
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

    protected function resolveHealthStatus(): array
    {
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $queueDriver = config('queue.default', 'sync');

        return [
            'database' => [
                'label' => 'Database',
                'ok' => $dbOk,
                'details' => $dbOk ? 'Connected' : 'Unavailable',
            ],
            'queue' => [
                'label' => 'Queue',
                'ok' => $queueDriver !== 'sync',
                'details' => $queueDriver === 'sync' ? 'sync (disabled)' : $queueDriver,
            ],
            'realtime' => $this->resolveRealtimeHealth(),
        ];
    }

    protected function countActiveUsers(): int
    {
        return Cache::remember('admin:active_users', 60, function () {
            try {
                return DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id');
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }

    protected function resolveRealtimeHealth(): array
    {
        $driver = config('broadcasting.default', 'log');
        $connections = config('broadcasting.connections', []);
        $connection = $connections[$driver] ?? [];
        $ok = false;
        $details = $driver;
        $missing = [];

        if ($driver === 'reverb') {
            $required = ['key', 'secret', 'app_id'];
            foreach ($required as $key) {
                if (empty($connection[$key])) {
                    $missing[] = $key;
                }
            }

            if (!empty($missing)) {
                return [
                    'label' => 'Realtime',
                    'ok' => false,
                    'details' => 'Missing: '.implode(', ', $missing),
                ];
            }

            $host = $connection['options']['host'] ?? request()->getHost() ?? '127.0.0.1';
            $port = (int) ($connection['options']['port'] ?? 443);
            $errno = 0;
            $errstr = null;
            $socket = @fsockopen($host, $port, $errno, $errstr, 1.5);
            if ($socket) {
                fclose($socket);
                $ok = true;
                $details = "reverb: {$host}:{$port}";
            } else {
                $ok = false;
                $details = "reverb offline ({$host}:{$port})";
            }
        } elseif ($driver === 'pusher') {
            $required = ['key', 'secret', 'app_id'];
            foreach ($required as $key) {
                if (empty($connection[$key])) {
                    $missing[] = $key;
                }
            }
            $ok = empty($missing);
            $details = $ok ? 'pusher' : 'Missing: '.implode(', ', $missing);
        } elseif ($driver === 'ably') {
            $ok = !empty($connection['key']);
            $details = $ok ? 'ably' : 'Missing: key';
        } elseif (in_array($driver, ['log', 'null'], true)) {
            $ok = false;
            $details = $driver.' (disabled)';
        } else {
            $ok = !empty($connection);
            $details = $connection ? $driver : $driver.' (unconfigured)';
        }

        return [
            'label' => 'Realtime',
            'ok' => $ok,
            'details' => $details,
        ];
    }
}

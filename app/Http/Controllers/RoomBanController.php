<?php

namespace App\Http\Controllers;

use App\Events\ParticipantBanned;
use App\Events\ParticipantUnbanned;
use App\Models\Participant;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RoomBanController extends Controller
{
    private static ?bool $banIdentityColumns = null;

    public function store(Request $request, Room $room)
    {
        $this->ensureOwner($room);

        $data = $request->validate([
            'participant_id' => [
                'required',
                'integer',
                Rule::exists('participants', 'id')->where('room_id', $room->id),
            ],
        ]);

        $participant = Participant::findOrFail($data['participant_id']);

        $hasIdentityColumns = $this->bansHaveIdentityColumns();

        $banData = [
            'participant_id' => $participant->id,
            'display_name' => $participant->display_name,
        ];

        if ($hasIdentityColumns) {
            $banData['ip_address'] = $participant->ip_address ?? $request->ip();
            $banData['fingerprint'] = $participant->fingerprint ?? $request->cookie('lc_fp');
        }

        $ban = $room->bans()->firstOrCreate(
            [
                'session_token' => $participant->session_token,
                'room_id' => $room->id,
            ],
            $banData
        );

        $banCount = $room->bans()->count();

        try {
            event(new ParticipantBanned(
                $room->id,
                $room->slug,
                $ban->id,
                $participant->id,
                $ban->display_name ?? $participant->display_name ?? 'Guest',
                $ban->created_at?->toIso8601String(),
                $banCount,
                Auth::id(),
                null
            ));
        } catch (\Throwable $e) {
            // Broadcast failures should not block the ban action.
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'banned',
                'ban' => [
                    'id' => $ban->id,
                    'participant_id' => $participant->id,
                    'display_name' => $ban->display_name ?? $participant->display_name ?? 'Guest',
                    'banned_at' => $ban->created_at?->toIso8601String(),
                    'banned_at_human' => $ban->created_at?->diffForHumans(null, true) ?? 'just now',
                ],
                'ban_count' => $banCount,
            ]);
        }

        return back()->with('status', 'Participant banned from this room.');
    }

    public function destroy(Room $room, int $banId)
    {
        $this->ensureOwner($room);

        $ban = $room->bans()->findOrFail($banId);
        $participantId = $ban->participant_id ?? 0;
        $ban->delete();

        $banCount = $room->bans()->count();

        try {
            event(new ParticipantUnbanned(
                $room->id,
                $room->slug,
                $banId,
                $participantId,
                $banCount,
                Auth::id(),
                null
            ));
        } catch (\Throwable $e) {
            // Broadcast failures should not block the unban action.
        }

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'unbanned',
                'ban' => [
                    'id' => $banId,
                    'participant_id' => $participantId,
                ],
                'ban_count' => $banCount,
            ]);
        }

        return back()->with('status', 'Participant unbanned.');
    }

    protected function ensureOwner(Room $room): void
    {
        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }
    }

    protected function bansHaveIdentityColumns(): bool
    {
        if (self::$banIdentityColumns !== null) {
            return self::$banIdentityColumns;
        }

        return self::$banIdentityColumns = Schema::hasColumns('room_bans', ['ip_address', 'fingerprint']);
    }
}

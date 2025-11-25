<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RoomBanController extends Controller
{
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

        $hasIdentityColumns = Schema::hasColumn('room_bans', 'ip_address')
            && Schema::hasColumn('room_bans', 'fingerprint');

        $banData = [
            'participant_id' => $participant->id,
            'display_name' => $participant->display_name,
        ];

        if ($hasIdentityColumns) {
            $banData['ip_address'] = $participant->ip_address ?? $request->ip();
            $banData['fingerprint'] = $participant->fingerprint ?? $request->cookie('lc_fp');
        }

        $room->bans()->firstOrCreate(
            [
                'session_token' => $participant->session_token,
                'room_id' => $room->id,
            ],
            $banData
        );

        return back()->with('status', 'Participant banned from this room.');
    }

    public function destroy(Room $room, int $banId)
    {
        $this->ensureOwner($room);

        $ban = $room->bans()->findOrFail($banId);
        $ban->delete();

        return back()->with('status', 'Participant unbanned.');
    }

    protected function ensureOwner(Room $room): void
    {
        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }
    }
}

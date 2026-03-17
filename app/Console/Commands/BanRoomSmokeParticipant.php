<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use Illuminate\Console\Command;

class BanRoomSmokeParticipant extends Command
{
    protected $signature = 'smoke:ban-room-participant
        {room : Room ID or slug}
        {participant : Participant ID}
        {--force : Allow running in production}';

    protected $description = 'Ban a room participant for browser smoke tests';

    public function handle(): int
    {
        if (! $this->option('force') && app()->environment('production')) {
            $this->warn('Refusing to modify browser smoke bans in production without --force.');

            return self::FAILURE;
        }

        $room = $this->findRoom((string) $this->argument('room'));
        if (! $room) {
            $this->error('Room not found.');

            return self::FAILURE;
        }

        $participantId = (int) $this->argument('participant');
        $participant = Participant::query()
            ->whereKey($participantId)
            ->where('room_id', $room->id)
            ->first();

        if (! $participant) {
            $this->error('Participant not found in the room.');

            return self::FAILURE;
        }

        RoomBan::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_token' => $participant->session_token,
            ],
            [
                'participant_id' => $participant->id,
                'display_name' => $participant->display_name,
                'ip_address' => $participant->ip_address,
                'fingerprint' => $participant->fingerprint,
            ],
        );

        $this->info('Smoke participant banned for room '.$room->slug.'.');

        return self::SUCCESS;
    }

    protected function findRoom(string $value): ?Room
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return Room::query()
            ->when(ctype_digit($trimmed), fn ($query) => $query->orWhere($query->getModel()->getKeyName(), (int) $trimmed))
            ->orWhere('slug', $trimmed)
            ->first();
    }
}

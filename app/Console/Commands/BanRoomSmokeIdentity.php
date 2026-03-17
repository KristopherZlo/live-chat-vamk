<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomBan;
use Illuminate\Console\Command;

class BanRoomSmokeIdentity extends Command
{
    protected $signature = 'smoke:ban-room-identity
        {room : Room ID or slug}
        {fingerprint : Fingerprint to ban}
        {--force : Allow running in production}';

    protected $description = 'Ban a room identity by fingerprint for browser smoke tests';

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

        $fingerprint = trim((string) $this->argument('fingerprint'));
        if ($fingerprint === '') {
            $this->error('Fingerprint is required.');

            return self::FAILURE;
        }

        RoomBan::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_token' => 'smoke:'.$fingerprint,
            ],
            [
                'participant_id' => null,
                'display_name' => 'Smoke banned identity',
                'ip_address' => null,
                'fingerprint' => $fingerprint,
            ],
        );

        $this->info('Smoke identity banned for room '.$room->slug.'.');

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

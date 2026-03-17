<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\MessagePollOption;
use App\Models\MessagePollVote;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedRoomSmoke extends Command
{
    protected $signature = 'smoke:seed-room
        {--slug=smoke-room : Room slug to recreate}
        {--host-email=smoke-host@example.test : Host email}
        {--host-password=password : Host password}
        {--json : Output fixture metadata as JSON}
        {--force : Allow running in production}';

    protected $description = 'Seed a deterministic room fixture for browser smoke tests';

    public function handle(): int
    {
        if (! $this->option('force') && app()->environment('production')) {
            $this->warn('Refusing to seed browser smoke data in production without --force.');

            return self::FAILURE;
        }

        $slug = trim((string) $this->option('slug'));
        $slug = $slug !== '' ? $slug : 'smoke-room';
        $hostEmail = trim((string) $this->option('host-email'));
        $hostPassword = (string) $this->option('host-password');

        $fixture = DB::transaction(function () use ($slug, $hostEmail, $hostPassword): array {
            $host = User::updateOrCreate(
                ['email' => $hostEmail],
                [
                    'name' => 'Smoke Host',
                    'password' => $hostPassword,
                    'email_verified_at' => now(),
                    'registration_ip' => '127.0.0.1',
                ],
            );

            $existingRoom = Room::query()->where('slug', $slug)->first();
            if ($existingRoom) {
                $existingRoom->delete();
            }

            $room = Room::create([
                'user_id' => $host->id,
                'title' => 'Smoke Test Room',
                'description' => 'Deterministic room fixture for browser smoke tests.',
                'slug' => $slug,
                'status' => 'active',
                'card_color' => Room::CARD_COLORS[0],
                'sort_order' => 0,
                'is_public_read' => true,
            ]);

            $bot = Participant::create([
                'room_id' => $room->id,
                'session_token' => 'smoke-bot-session',
                'display_name' => 'Smoke Bot',
                'ip_address' => '127.0.0.2',
                'fingerprint' => 'smoke-bot-fingerprint',
            ]);

            $hostMessage = Message::create([
                'room_id' => $room->id,
                'user_id' => $host->id,
                'participant_id' => null,
                'is_system' => false,
                'content' => 'Smoke host message',
            ]);

            Message::create([
                'room_id' => $room->id,
                'user_id' => null,
                'participant_id' => $bot->id,
                'reply_to_id' => $hostMessage->id,
                'is_system' => false,
                'content' => 'Smoke reply from participant',
            ]);

            MessageReaction::create([
                'message_id' => $hostMessage->id,
                'user_id' => null,
                'participant_id' => $bot->id,
                'emoji' => "\u{1F44D}",
            ]);

            $questionMessage = Message::create([
                'room_id' => $room->id,
                'user_id' => null,
                'participant_id' => $bot->id,
                'is_system' => false,
                'content' => 'Smoke seeded question',
            ]);

            $question = Question::create([
                'room_id' => $room->id,
                'message_id' => $questionMessage->id,
                'participant_id' => $bot->id,
                'user_id' => null,
                'content' => $questionMessage->content,
                'status' => 'new',
            ]);

            $pollMessage = Message::create([
                'room_id' => $room->id,
                'user_id' => $host->id,
                'participant_id' => null,
                'is_system' => false,
                'content' => 'Choose the smoke option',
            ]);

            $poll = MessagePoll::create([
                'message_id' => $pollMessage->id,
                'question' => 'Choose the smoke option',
                'is_closed' => false,
            ]);

            $pollOptions = collect([
                ['label' => 'Option Alpha', 'position' => 0],
                ['label' => 'Option Beta', 'position' => 1],
            ])->map(fn (array $option) => MessagePollOption::create([
                'poll_id' => $poll->id,
                'label' => $option['label'],
                'position' => $option['position'],
            ]));

            MessagePollVote::create([
                'poll_id' => $poll->id,
                'option_id' => $pollOptions->first()->id,
                'user_id' => null,
                'participant_id' => $bot->id,
            ]);

            return [
                'host' => [
                    'email' => $host->email,
                    'password' => $hostPassword,
                ],
                'room' => [
                    'id' => $room->id,
                    'slug' => $room->slug,
                    'path' => '/r/'.$room->slug,
                ],
                'question' => [
                    'id' => $question->id,
                ],
            ];
        });

        if ($this->option('json')) {
            $this->line(json_encode($fixture, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Browser smoke room fixture seeded.');
        $this->line('Room: '.$fixture['room']['path']);
        $this->line('Host email: '.$fixture['host']['email']);

        return self::SUCCESS;
    }
}

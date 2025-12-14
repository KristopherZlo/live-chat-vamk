<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedMessages extends Command
{
    protected $signature = 'chat:seed-messages {room : Room ID or slug} {--count=200} {--delay=1-5} {--force : Run even outside local/dev}';

    protected $description = 'Seed a room with participant chat messages with a random delay between each';

    public function handle(): int
    {
        if (!$this->option('force') && app()->environment('production')) {
            $this->warn('Refusing to run in production without --force');
            return self::FAILURE;
        }

        $room = $this->findRoom($this->argument('room'));
        if (!$room) {
            $this->error('Room not found.');
            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        [$delayMin, $delayMax] = $this->parseDelay($this->option('delay'));

        $participants = $this->seedParticipants($room, 5);
        $lines = $this->messageLines();

        $this->info("Seeding {$count} messages into room {$room->id} ({$room->slug})");

        for ($i = 0; $i < $count; $i++) {
            $participant = $participants->random();
            $content = $lines[array_rand($lines)];

            $message = Message::create([
                'room_id' => $room->id,
                'participant_id' => $participant->id,
                'content' => $content,
                'is_system' => false,
            ]);

            event(new MessageSent($message));

            $this->output->write('.');
            $this->sleepRandom($delayMin, $delayMax);
        }

        $this->newLine(2);
        $this->info('Done.');

        return self::SUCCESS;
    }

    protected function findRoom(string $input): ?Room
    {
        return Room::where('id', $input)
            ->orWhere('slug', $input)
            ->first();
    }

    protected function seedParticipants(Room $room, int $count = 5)
    {
        $names = collect([
            'Alex', 'Jordan', 'Taylor', 'Sam', 'Morgan', 'Riley', 'Casey', 'Dana', 'Quinn', 'Avery',
            'Jamie', 'Cameron', 'Skyler', 'Harper', 'Charlie',
        ])->shuffle()->take($count);

        return $names->map(function ($name) use ($room) {
            return Participant::create([
                'room_id' => $room->id,
                'session_token' => (string) Str::uuid(),
                'display_name' => $name,
            ]);
        });
    }

    protected function parseDelay(string $raw): array
    {
        if (preg_match('/^(\\d+(?:\\.\\d+)?)-(\\d+(?:\\.\\d+)?)$/', $raw, $m)) {
            $a = (float) $m[1];
            $b = (float) $m[2];
            return [min($a, $b), max($a, $b)];
        }
        $sec = max(0.0, (float) $raw);
        return [$sec, $sec];
    }

    protected function sleepRandom(float $min, float $max): void
    {
        $delay = $min === $max ? $min : random_int((int) round($min * 1000), (int) round($max * 1000)) / 1000;
        if ($delay <= 0) {
            return;
        }
        usleep((int) round($delay * 1_000_000));
    }

    protected function messageLines(): array
    {
        return [
            'Hi all, does anyone remember the steps from yesterday?',
            'I think we should start with the basics before diving deeper.',
            'That example in slide 4 was confusing; can someone clarify?',
            'What is the expected output format for the assignment?',
            'Do we have to optimize for performance here?',
            'I tried the snippet but got a different result.',
            'Any tips for debugging this quickly?',
            'Is this supposed to work on mobile as well?',
            'I like the new UI changes!',
            'Could you share the repo link again?',
            'This looks similar to the previous module.',
            'I will push a quick fix in a minute.',
            'Anyone tried running it on Windows?',
            'Let’s pair on this after the session.',
            'Coffee break? (just kidding, back to work!)',
            'I got a 500 error; checking logs now.',
            'The docs mention an edge case, did we cover it?',
            'Nice catch on that typo!',
            'I will add a test for this scenario.',
            'Can we postpone the Q&A to the end?',
        ];
    }
}

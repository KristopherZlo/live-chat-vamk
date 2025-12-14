<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Events\QuestionCreated;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedQuestions extends Command
{
    protected $signature = 'chat:seed-questions {room : Room ID or slug} {--count=50} {--delay=1} {--force : Run even outside local/dev}';

    protected $description = 'Seed a room with questions (message + question) with a delay between each';

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

        $participants = $this->seedParticipants($room, 3);
        $questionsPool = $this->questionLines();

        $this->info("Seeding {$count} questions into room {$room->id} ({$room->slug})");

        for ($i = 0; $i < $count; $i++) {
            $participant = $participants->random();
            $content = $questionsPool[array_rand($questionsPool)];

            $message = Message::create([
                'room_id' => $room->id,
                'participant_id' => $participant->id,
                'content' => $content,
                'is_system' => false,
            ]);

            $question = Question::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'participant_id' => $participant->id,
                'content' => $content,
                'status' => 'new',
            ]);

            $message->setRelation('question', $question);

            event(new MessageSent($message));
            event(new QuestionCreated($question));

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

    protected function seedParticipants(Room $room, int $count = 3)
    {
        $names = collect([
            'Alex', 'Jordan', 'Taylor', 'Sam', 'Morgan', 'Riley', 'Casey', 'Dana', 'Quinn', 'Avery',
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

    protected function questionLines(): array
    {
        return [
            'Could you clarify the last point?',
            'Is there a shortcut to remember this flow?',
            'Where can I read more about this?',
            'What is the expected output format?',
            'Any pitfalls we should avoid?',
            'How deep should we go on this topic?',
            'Can you show a concrete example?',
            'Is this covered on the exam?',
            'How do we handle edge cases here?',
            'Can we get a quick recap?',
            'What are the performance limits?',
            'Is there a best practice for this?',
            'What if the input is malformed?',
            'How would you test this scenario?',
            'Could you repeat the requirements?',
            'Is there a simpler alternative?',
            'What libraries can help with this?',
            'Do we need to support async here?',
            'How do we debug this efficiently?',
            'Any recommended patterns for this?',
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\QuestionCreated;
use App\Events\ReactionUpdated;
use App\Models\MessageReaction;
use App\Events\QuestionUpdated;
use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SeedDemoChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:seed-demo {room : Room ID or slug} {--count= : Number of messages (default: random 5-30)} {--delay=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a room with demo participant messages, replies, deletions, and host Q&A (testing only)';

    public function handle(): int
    {
        if (!app()->environment(['testing', 'local', 'development'])) {
            $this->error('This command is only allowed in testing/local environments.');
            return self::FAILURE;
        }

        $roomInput = $this->argument('room');
        $countOption = $this->option('count');
        $targetCount = is_numeric($countOption)
            ? max(1, min(200, (int) $countOption)) // allow larger simulations, but clamp
            : random_int(5, 30);
        $delayOption = $this->option('delay');
        [$delayMin, $delayMax] = $this->parseDelay($delayOption !== null ? (string) $delayOption : '0');

        /** @var Room|null $room */
        $room = Room::with('owner')
            ->where('id', $roomInput)
            ->orWhere('slug', $roomInput)
            ->first();

        if (!$room) {
            $this->error('Room not found.');
            return self::FAILURE;
        }

        $host = $room->owner;

        $usedNames = [];
        $participantCount = random_int(3, 6);
        $participants = collect(range(1, $participantCount))
            ->map(function () use ($room, &$usedNames) {
                do {
                    $name = 'user' . random_int(100, 999);
                } while (in_array($name, $usedNames, true));
                $usedNames[] = $name;
                return $this->createParticipant($room, $name);
            });

        $now = Carbon::now();

        $conversationStarters = [
            'Hey everyone, ready for the session?',
            'I had a question about the last topic we covered.',
            'Does anyone have a good mnemonic for this formula?',
            'I tried the homework, and step 3 confused me.',
            'Quick check: do we submit today or tomorrow?',
            'Thanks for clarifying earlier, that helped a lot.',
            'I got stuck on the diagram on slide 6.',
            'Is there a shortcut to remember the core steps?',
            'My code works locally but fails in the example—any ideas?',
            'Can we review the edge cases for this algorithm?',
            'Is the group project still due next week?',
            'What is the simplest way to test this feature?',
            'How deep do we need to go for the optional part?',
            'Could you recap the main takeaway in one sentence?',
            'I tried the new syntax, but the linter complains.',
            'Are there recommended readings to go deeper?',
            'What is the grading weight for this section?',
        ];

        $replyLines = [
            'Ah, that makes sense now.',
            'Could you expand on that with an example?',
            'Following up on this — where can I read more?',
            'I think the answer is in the second slide deck.',
            'Good catch! I missed that detail.',
            'Same question here, also curious.',
            'Here’s a quick tip: write it down as a flow.',
            'I’d try visualizing it; that helped me.',
            'Got it, thanks!',
            'Does this apply to async tasks too?',
        ];

        $hostLines = [
            'Welcome! Let’s warm up with a quick recap.',
            'I’ll share a link with extra examples in a moment.',
            'Yes, the deadline is tomorrow at noon.',
            'Good question — think about the data flow first.',
        ];

        // Override with richer demo pools
        $conversationStarters = [
            'Hey everyone, ready for the session?',
            'I had a question about the last topic we covered.',
            'Does anyone have a good mnemonic for this formula?',
            'I tried the homework, and step 3 confused me.',
            'Quick check: do we submit today or tomorrow?',
            'Thanks for clarifying earlier, that helped a lot.',
            'I got stuck on the diagram on slide 6.',
            'Is there a shortcut to remember the core steps?',
            'My code works locally but fails in the example—any ideas?',
            'Can we review the edge cases for this algorithm?',
            'Is the group project still due next week?',
            'What is the simplest way to test this feature?',
            'How deep do we need to go for the optional part?',
            'Could you recap the main takeaway in one sentence?',
            'I tried the new syntax, but the linter complains.',
            'Are there recommended readings to go deeper?',
            'What is the grading weight for this section?',
        ];

        $replyLines = [
            'Ah, that makes sense now.',
            'Could you expand on that with an example?',
            'Following up on this — where can I read more?',
            'I think the answer is in the second slide deck.',
            'Good catch! I missed that detail.',
            'Same question here, also curious.',
            'Here is a quick tip: write it down as a flow.',
            'I would try visualizing it; that helped me.',
            'Got it, thanks!',
            'Does this apply to async tasks too?',
            'I will try that and report back.',
            'Sharing a snippet that worked for me.',
            'Maybe the config is missing an entry?',
            'I think we need to reset the cache for that.',
            'Try stepping through with a debugger.',
            'I saw a similar issue on the forum yesterday.',
            'Could the dataset size be the problem?',
            'Nice catch, I did not think of that.',
        ];

        $hostLines = [
            'Welcome! Let’s warm up with a quick recap.',
            'I’ll share a link with extra examples in a moment.',
            'Yes, the deadline is tomorrow at noon.',
            'Good question — think about the data flow first.',
            'Focus on clarity over brevity for this exercise.',
            'Remember to test with both happy and edge paths.',
            'The rubric is posted in the docs section.',
            'Start with a small prototype, then iterate.',
        ];

        $messages = collect();

        // Seed a few host messages first (if a host exists).
        if ($host) {
            $hostSeeds = Arr::random($hostLines, min(count($hostLines), 3));
            foreach ($hostSeeds as $index => $line) {
                if ($messages->count() >= $targetCount) {
                    break;
                }
                $messages->push($this->createMessage(
                    room: $room,
                    content: $line,
                    userId: $host->id,
                    createdAt: $now->copy()->subMinutes(20 - $index)
                ));
                $this->sleepRandom($delayMin, $delayMax);
            }
        }

        // Seed base participant conversation.
        $starterCount = min(5, max(0, $targetCount - $messages->count()));
        foreach (Arr::random($conversationStarters, $starterCount) as $index => $line) {
            if ($messages->count() >= $targetCount) {
                break;
            }
            $participant = $participants->random();
            $messages->push($this->createMessage(
                room: $room,
                content: $line,
                participantId: $participant->id,
                createdAt: $now->copy()->subMinutes(15 - $index)
            ));
            $this->sleepRandom($delayMin, $delayMax);
        }

        // Generate replies, nested replies, and questions.
        // Use the requested count (bounded above) for reply generation so it is predictable.
        $replyCount = max(0, $targetCount - $messages->count());
        $replyParents = [];

        for ($i = 0; $i < $replyCount; $i++) {
            if ($messages->count() >= $targetCount) {
                break;
            }
            if ($messages->isEmpty()) {
                break;
            }

            /** @var Message $parent */
            $parent = $messages->random();
            $authorIsHost = $host && random_int(0, 4) === 0; // 20% chance host replies.
            $authorParticipant = $participants->random();
            $isQuestion = !$authorIsHost && random_int(0, 5) === 0; // some participant replies are questions.

            $content = Arr::random($replyLines);
            if ($isQuestion && random_int(0, 1) === 1) {
                $content = 'Question: ' . $content;
            }

            $message = $this->createMessage(
                room: $room,
                content: $content,
                userId: $authorIsHost ? $host?->id : null,
                participantId: $authorIsHost ? null : $authorParticipant->id,
                replyToId: $parent->id,
                asQuestion: $isQuestion,
                createdAt: $parent->created_at->copy()->addMinutes(random_int(1, 12))
            );

            $messages->push($message);
            $replyParents[] = $parent->id;
            $this->sleepRandom($delayMin, $delayMax);

            // Occasionally add a reply to this reply (nested).
            if ($messages->count() < $targetCount && random_int(0, 3) === 0) {
                $nestedAuthor = $participants->random();
                $nestedContent = Arr::random($replyLines);
                $messages->push($this->createMessage(
                    room: $room,
                    content: $nestedContent,
                    participantId: $nestedAuthor->id,
                    replyToId: $message->id,
                    createdAt: $message->created_at->copy()->addMinutes(random_int(1, 6))
                ));
                $replyParents[] = $message->id;
                $this->sleepRandom($delayMin, $delayMax);
            }
        }

        // Soft-delete a couple of messages that have replies.
        $replyParents = collect(array_unique($replyParents));
        if ($replyParents->isNotEmpty()) {
            $toDelete = $replyParents->shuffle()->take(random_int(1, min(3, $replyParents->count())));
            foreach ($toDelete as $messageId) {
                /** @var Message|null $target */
                $target = $messages->firstWhere('id', $messageId);
                if (!$target || $target->trashed()) {
                    continue;
                }

                if ($host && random_int(0, 1) === 0) {
                    $target->deleted_by_user_id = $host->id;
                } else {
                    $deleter = $participants->random();
                    $target->deleted_by_participant_id = $deleter->id;
                }
                $target->save();
                $target->delete();

                $question = $target->question()->first();
                if ($question) {
                    if ($target->deleted_by_user_id) {
                        $question->deleted_by_owner_at = Carbon::now();
                    } else {
                        $question->deleted_by_participant_at = Carbon::now();
                    }
                    $question->save();
                    event(new QuestionUpdated($question));
                }

                event(new MessageDeleted(
                    $target->id,
                    $room->id,
                    $room->slug,
                    $target->deleted_by_user_id,
                    $target->deleted_by_participant_id
                ));
            }
        }

        $this->seedReactions($messages, $room, $participants, $host);

        $this->info("Seeded {$messages->count()} messages into room {$room->id}.");

        return self::SUCCESS;
    }

    private function createParticipant(Room $room, string $name): Participant
    {
        return Participant::create([
            'room_id' => $room->id,
            'session_token' => (string) Str::uuid(),
            'display_name' => $name,
        ]);
    }

    private function parseDelay(string $raw): array
    {
        if (preg_match('/^(\\d+(?:\\.\\d+)?)-(\\d+(?:\\.\\d+)?)$/', $raw, $m)) {
            $a = (float) $m[1];
            $b = (float) $m[2];
            return [min($a, $b), max($a, $b)];
        }
        $sec = max(0.0, (float) $raw);
        return [$sec, $sec];
    }

    private function sleepRandom(float $min, float $max): void
    {
        $delay = $min === $max ? $min : random_int((int) round($min * 1000), (int) round($max * 1000)) / 1000;
        if ($delay <= 0) {
            return;
        }
        usleep((int) round($delay * 1_000_000));
    }

    private function createMessage(
        Room $room,
        string $content,
        ?int $userId = null,
        ?int $participantId = null,
        ?int $replyToId = null,
        bool $asQuestion = false,
        ?Carbon $createdAt = null
    ): Message {
        $message = Message::create([
            'room_id' => $room->id,
            'participant_id' => $participantId,
            'user_id' => $userId,
            'reply_to_id' => $replyToId,
            'is_system' => false,
            'content' => $content,
        ]);

        if ($createdAt) {
            $message->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $question = null;
        if ($asQuestion) {
            $question = Question::create([
                'room_id' => $room->id,
                'message_id' => $message->id,
                'participant_id' => $participantId,
                'user_id' => $userId,
                'content' => $content,
                'status' => 'new',
            ]);
            $message->setRelation('question', $question);
        }

        // Broadcast to Echo subscribers so the chat updates live.
        $message->loadMissing(['user', 'participant', 'room', 'replyTo.user', 'replyTo.participant', 'reactions']);
        event(new MessageSent($message));
        if ($question) {
            event(new QuestionCreated($question));
        }

        return $message;
    }

    private function seedReactions($messages, Room $room, $participants, $host): void
    {
        if ($messages->isEmpty()) {
            return;
        }

        $emojis = ['👍', '👏', '🔥', '🎯', '🤔', '🙌', '😊', '💡'];
        $actorsPool = $participants->values();
        if ($host) {
            $actorsPool->push($host);
        }

        foreach ($messages as $message) {
            if ($actorsPool->isEmpty()) {
                continue;
            }

            $totalReactions = random_int(0, 15);
            if ($totalReactions === 0) {
                continue;
            }

            $prevEmoji = null;
            for ($i = 0; $i < $totalReactions; $i++) {
                $actor = $actorsPool->random();
                $actorIsHost = $host && $actor && $actor->id === ($host->id ?? null);
                $emoji = (random_int(0, 3) === 0 && $prevEmoji) ? $prevEmoji : Arr::random($emojis);
                $prevEmoji = $emoji;

                MessageReaction::updateOrCreate(
                    [
                        'message_id' => $message->id,
                        'user_id' => $actorIsHost ? $actor->id : null,
                        'participant_id' => $actorIsHost ? null : $actor->id,
                    ],
                    [
                        'emoji' => $emoji,
                    ]
                );
            }

            $summary = $this->summarizeReactions($message->id);
            $yourReactions = [];

            event(new ReactionUpdated(
                $room->id,
                $room->slug,
                $message->id,
                $summary,
                $yourReactions,
                null,
                null
            ));
        }
    }

    private function summarizeReactions(int $messageId): array
    {
        $grouped = MessageReaction::where('message_id', $messageId)
            ->get()
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
            ])
            ->values();

        return $grouped
            ->sort(function ($a, $b) {
                $countDiff = $b['count'] <=> $a['count'];
                if ($countDiff !== 0) {
                    return $countDiff;
                }
                return strcasecmp($a['emoji'], $b['emoji']);
            })
            ->values()
            ->toArray();
    }
}

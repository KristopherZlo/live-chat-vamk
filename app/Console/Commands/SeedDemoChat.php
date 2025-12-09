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
    protected $signature = 'chat:seed-demo {room : Room ID or slug} {--count= : Number of messages (default: random 5-30)}';

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
            ? max(5, min(60, (int) $countOption))
            : random_int(5, 30);

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

        $participantNames = [
            'Alex', 'Casey', 'Jordan', 'Taylor', 'Sam', 'Morgan', 'Riley', 'Drew',
        ];

        $participants = collect($participantNames)
            ->shuffle()
            ->take(random_int(3, 6))
            ->map(fn ($name) => $this->createParticipant($room, $name));

        $now = Carbon::now();

        $conversationStarters = [
            'Hey everyone, ready for the session?',
            'I had a question about the last topic we covered.',
            'Does anyone have a good mnemonic for this formula?',
            'I tried the homework, and step 3 confused me.',
            'Quick check: do we submit today or tomorrow?',
            'Thanks for clarifying earlier, that helped a lot.',
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

        $messages = collect();

        // Seed a few host messages first (if a host exists).
        if ($host) {
            foreach (Arr::random($hostLines, min(count($hostLines), 3)) as $index => $line) {
                $messages->push($this->createMessage(
                    room: $room,
                    content: $line,
                    userId: $host->id,
                    createdAt: $now->copy()->subMinutes(20 - $index)
                ));
            }
        }

        // Seed base participant conversation.
        foreach (Arr::random($conversationStarters, min(count($conversationStarters), 5)) as $index => $line) {
            $participant = $participants->random();
            $messages->push($this->createMessage(
                room: $room,
                content: $line,
                participantId: $participant->id,
                createdAt: $now->copy()->subMinutes(15 - $index)
            ));
        }

        // Generate replies, nested replies, and questions.
        $replyCount = max(5, min(30, $targetCount));
        $replyParents = [];

        for ($i = 0; $i < $replyCount; $i++) {
            if ($messages->isEmpty()) {
                break;
            }

            /** @var Message $parent */
            $parent = $messages->random();
            $authorIsHost = $host && random_int(0, 4) === 0; // 20% chance host replies.
            $authorParticipant = $participants->random();
            $isQuestion = !$authorIsHost && random_int(0, 5) === 0; // some participant replies are questions.

            $content = Arr::random($replyLines);
            if ($isQuestion) {
                $content = 'Question: ' . Str::lower($content);
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

            // Occasionally add a reply to this reply (nested).
            if (random_int(0, 3) === 0) {
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
        foreach ($messages as $message) {
            // Some messages may have zero reactions
            if (random_int(0, 3) === 0) {
                continue;
            }

            $reactorCount = random_int(1, max(2, (int) floor($participants->count() / 2)));
            $actors = $participants->shuffle()->take($reactorCount)->values();

            // Optionally include host as a reactor
            if ($host && random_int(0, 1) === 0) {
                $actors->push($host);
            }

            foreach ($actors as $actor) {
                $actorIsHost = $host && $actor && $actor->id === ($host->id ?? null);
                $emoji = Arr::random($emojis);

                MessageReaction::updateOrCreate(
                    [
                        'message_id' => $message->id,
                        'user_id' => $actorIsHost ? $actor->id : null,
                        'participant_id' => $actorIsHost ? null : $actor->id,
                        'emoji' => $emoji,
                    ],
                    []
                );
            }

            $summary = $this->summarizeReactions($message->id);
            $yourReactions = [];

            event(new ReactionUpdated(
                $room->id,
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

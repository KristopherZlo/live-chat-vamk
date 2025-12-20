<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Events\PollUpdated;
use App\Events\ReactionUpdated;
use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeedPoll extends Command
{
    protected $signature = 'chat:seed-poll {room : Room ID or slug} {--options= : Number of options (default: random 3-5)} {--votes= : Votes to generate (default: random 20-40)} {--replies= : Replies to add (default: random 2-6)} {--reactions= : Reactions to add (default: random 2-8)} {--participants= : Participants to use/create (default: 5)} {--force : Run even outside local/dev}';

    protected $description = 'Seed a room with one host poll plus random votes, replies, and reactions';

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

        $room->loadMissing('owner');
        $host = $room->owner;
        if (!$host) {
            $this->error('Room owner not found.');
            return self::FAILURE;
        }

        $votesCount = $this->resolveCountOption('votes', 20, 40);
        $participantsCount = $this->resolveParticipantsCount($votesCount);
        $participants = $this->loadParticipants($room, $participantsCount);

        $pollSeed = Arr::random($this->pollSeeds());
        $optionsCount = $this->resolveCountOption('options', 3, 5);
        $options = $this->buildOptions($pollSeed['options'], $optionsCount);
        if (count($options) < 2) {
            $options = ['Option 1', 'Option 2'];
        }

        $now = Carbon::now();
        $message = $this->createPollMessage(
            room: $room,
            question: $pollSeed['question'],
            options: $options,
            userId: $host->id,
            createdAt: $now->copy()->subMinutes(5)
        );

        $poll = $message->poll;

        $repliesCount = $this->resolveCountOption('replies', 2, 6);
        $this->seedReplies($room, $message, $participants, $host->id, $repliesCount, $now);

        $this->seedVotes($room, $poll, $participants, $host->id, $votesCount);

        $reactionsCount = $this->resolveCountOption('reactions', 2, 8);
        $this->seedReactions($room, $message->id, $participants, $host->id, $reactionsCount);

        $this->info('Poll seeded with replies, votes, and reactions.');

        return self::SUCCESS;
    }

    protected function findRoom(string $input): ?Room
    {
        return Room::where('id', $input)
            ->orWhere('slug', $input)
            ->first();
    }

    protected function loadParticipants(Room $room, int $targetCount): Collection
    {
        $participants = Participant::where('room_id', $room->id)->get();
        $missing = max(0, $targetCount - $participants->count());

        if ($missing === 0) {
            return $participants;
        }

        $names = collect([
            'Alex', 'Jordan', 'Taylor', 'Sam', 'Morgan', 'Riley', 'Casey', 'Dana', 'Quinn', 'Avery',
            'Jamie', 'Cameron', 'Skyler', 'Harper', 'Charlie',
        ])->shuffle();

        for ($i = 0; $i < $missing; $i++) {
            $participants->push(Participant::create([
                'room_id' => $room->id,
                'session_token' => (string) Str::uuid(),
                'display_name' => $names->get($i, 'Guest ' . ($i + 1)),
            ]));
        }

        return $participants;
    }

    protected function pollSeeds(): array
    {
        return [
            [
                'question' => 'Which topic needs another example?',
                'options' => ['Loops', 'Arrays', 'Functions', 'Debugging'],
            ],
            [
                'question' => 'How confident do you feel about the homework?',
                'options' => ['Very', 'Somewhat', 'Need help'],
            ],
            [
                'question' => 'Preferred pace for the next section?',
                'options' => ['Slow', 'Medium', 'Fast'],
            ],
            [
                'question' => 'Pick the best time for a recap.',
                'options' => ['Start', 'Midway', 'End'],
            ],
        ];
    }

    protected function replyLines(): array
    {
        return [
            'Thanks for adding this!',
            'Can we cover this in more detail?',
            'I would vote for that option.',
            'Good idea, I agree.',
            'Not sure yet, but leaning toward that.',
            'Could you clarify what this means?',
            'I will share feedback after class.',
            'This is helpful, thanks!',
        ];
    }

    protected function buildOptions(array $baseOptions, int $count): array
    {
        $options = collect($baseOptions)->filter()->values();
        $count = max(2, $count);

        if ($options->count() >= $count) {
            return $options->shuffle()->take($count)->values()->all();
        }

        $result = $options->values()->all();
        $start = count($result) + 1;
        for ($i = $start; $i <= $count; $i++) {
            $result[] = 'Option ' . $i;
        }

        return $result;
    }

    protected function resolveCountOption(string $option, int $min, int $max): int
    {
        $raw = $this->option($option);
        if ($raw !== null && $raw !== '') {
            $value = (int) $raw;
            return max($min, min($max, $value));
        }

        return random_int($min, $max);
    }

    protected function resolveParticipantsCount(int $votesCount): int
    {
        $raw = $this->option('participants');
        $value = $raw !== null && $raw !== '' ? max(1, (int) $raw) : random_int(5, 12);
        $minParticipants = max(5, $votesCount - 1);

        return max($value, $minParticipants);
    }

    protected function createPollMessage(
        Room $room,
        string $question,
        array $options,
        int $userId,
        ?Carbon $createdAt = null
    ): Message {
        $message = Message::create([
            'room_id' => $room->id,
            'participant_id' => null,
            'user_id' => $userId,
            'reply_to_id' => null,
            'is_system' => false,
            'content' => $question,
        ]);

        $poll = MessagePoll::create([
            'message_id' => $message->id,
            'question' => $question,
            'is_closed' => false,
        ]);

        $poll->options()->createMany(
            collect($options)->values()->map(fn ($option, $index) => [
                'label' => Str::limit((string) $option, 120, ''),
                'position' => $index,
            ])->all()
        );

        if ($createdAt) {
            $message->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
            $poll->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $message->setRelation('poll', $poll->loadMissing('options'));
        $message->loadMissing(['user', 'participant', 'room', 'replyTo.user', 'replyTo.participant', 'reactions']);
        event(new MessageSent($message));

        return $message;
    }

    protected function seedReplies(
        Room $room,
        Message $message,
        Collection $participants,
        int $hostId,
        int $count,
        Carbon $baseTime
    ): void {
        if ($count <= 0 || $participants->isEmpty()) {
            return;
        }

        $lines = $this->replyLines();

        for ($i = 0; $i < $count; $i++) {
            $authorIsHost = random_int(0, 5) === 0;
            $participant = $participants->random();
            $content = Arr::random($lines);
            $createdAt = $baseTime->copy()->addMinutes($i + 1);

            $reply = Message::create([
                'room_id' => $room->id,
                'participant_id' => $authorIsHost ? null : $participant->id,
                'user_id' => $authorIsHost ? $hostId : null,
                'reply_to_id' => $message->id,
                'is_system' => false,
                'content' => $content,
            ]);

            $reply->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $reply->loadMissing(['user', 'participant', 'room', 'replyTo.user', 'replyTo.participant', 'reactions']);
            event(new MessageSent($reply));
        }
    }

    protected function seedVotes(
        Room $room,
        MessagePoll $poll,
        Collection $participants,
        int $hostId,
        int $count
    ): void {
        $poll->loadMissing('options');
        if ($poll->options->isEmpty()) {
            return;
        }

        $actors = $this->actorPool($participants, $hostId);
        if ($actors->isEmpty()) {
            return;
        }

        $count = min($count, $actors->count());
        $actors = $actors->shuffle()->take($count);

        foreach ($actors as $actor) {
            $optionId = $poll->options->random()->id;
            MessagePollVote::create([
                'poll_id' => $poll->id,
                'option_id' => $optionId,
                'user_id' => $actor['user_id'],
                'participant_id' => $actor['participant_id'],
            ]);
        }

        $payload = $this->buildPollPayload($poll);
        event(new PollUpdated(
            $room->id,
            $room->slug,
            $poll->message_id,
            $poll->id,
            $payload,
            null,
            null,
            null
        ));
    }

    protected function seedReactions(
        Room $room,
        int $messageId,
        Collection $participants,
        int $hostId,
        int $count
    ): void {
        $actors = $this->actorPool($participants, $hostId);
        if ($actors->isEmpty()) {
            return;
        }

        $emojis = ["\u{1F44D}", "\u{1F44F}", "\u{1F3AF}", "\u{1F525}", "\u{1F4A1}", "\u{2705}", "\u{1F914}", "\u{1F680}"];
        $count = min($count, $actors->count());
        $actors = $actors->shuffle()->take($count);

        foreach ($actors as $actor) {
            MessageReaction::updateOrCreate(
                [
                    'message_id' => $messageId,
                    'user_id' => $actor['user_id'],
                    'participant_id' => $actor['participant_id'],
                ],
                [
                    'emoji' => Arr::random($emojis),
                ]
            );
        }

        $summary = $this->summarizeReactions($messageId);
        event(new ReactionUpdated(
            $room->id,
            $room->slug,
            $messageId,
            $summary,
            [],
            null,
            null
        ));
    }

    protected function actorPool(Collection $participants, int $hostId): Collection
    {
        $actors = $participants->map(fn ($participant) => [
            'user_id' => null,
            'participant_id' => $participant->id,
        ])->values();

        $actors->push([
            'user_id' => $hostId,
            'participant_id' => null,
        ]);

        return $actors;
    }

    protected function buildPollPayload(MessagePoll $poll): array
    {
        $poll->loadMissing('options');

        $votes = MessagePollVote::query()
            ->select('option_id')
            ->where('poll_id', $poll->id)
            ->get();

        $counts = $votes->groupBy('option_id')->map->count();
        $totalVotes = $counts->sum();

        $options = $poll->options
            ->sortBy('position')
            ->map(function ($option) use ($counts, $totalVotes) {
                $votesCount = (int) ($counts->get($option->id, 0));
                $percent = $totalVotes > 0 ? (int) round(($votesCount / $totalVotes) * 100) : 0;
                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => $votesCount,
                    'percent' => $percent,
                ];
            })
            ->values()
            ->toArray();

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'options' => $options,
            'total_votes' => $totalVotes,
            'my_vote_id' => null,
            'is_closed' => (bool) $poll->is_closed,
        ];
    }

    protected function summarizeReactions(int $messageId): array
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

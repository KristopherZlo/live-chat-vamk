<?php

namespace App\Console\Commands;

use App\Events\MessageSent;
use App\Events\PollUpdated;
use App\Events\QuestionCreated;
use App\Events\QuestionUpdated;
use App\Events\ReactionUpdated;
use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionRating;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StreamDemoChat extends Command
{
    protected $signature = 'chat:stream-demo {room : Room ID or slug} {--delay=1-3 : Delay range between bot actions} {--participants=8 : Number of bot participants} {--force : Run even outside local/dev}';

    protected $description = 'Run a continuous stream of demo messages, replies, questions, reactions, and poll votes';

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

        $participantsCount = max(3, (int) $this->option('participants'));
        $participants = $this->loadBotParticipants($room, $participantsCount);
        if ($participants->isEmpty()) {
            $this->error('Unable to load demo participants.');
            return self::FAILURE;
        }

        [$delayMin, $delayMax] = $this->parseDelay((string) $this->option('delay'));

        $botIds = $participants->pluck('id')->all();
        $botLookup = array_fill_keys($botIds, true);

        $lastMessageId = (int) (Message::where('room_id', $room->id)->max('id') ?? 0);
        $lastReactionId = (int) (MessageReaction::whereHas('message', fn ($q) => $q->where('room_id', $room->id))->max('id') ?? 0);
        $lastPollId = (int) (MessagePoll::whereHas('message', fn ($q) => $q->where('room_id', $room->id))->max('id') ?? 0);

        $recentMessageIds = Message::where('room_id', $room->id)
            ->orderByDesc('id')
            ->limit(60)
            ->pluck('id')
            ->all();

        $pendingActions = [];
        $pendingPolls = [];
        $pendingRatings = [];

        $nextStreamAt = microtime(true) + $this->randomDelay($delayMin, $delayMax);
        $nextMessageScanAt = microtime(true);
        $nextReactionScanAt = microtime(true);
        $nextPollScanAt = microtime(true);
        $nextQuestionScanAt = microtime(true);

        $this->info("Streaming demo activity into room {$room->id} ({$room->slug})");

        while (true) {
            $now = microtime(true);

            $pendingActions = $this->processPendingActions($pendingActions, $room, $now);
            $pendingRatings = $this->processPendingRatings($pendingRatings, $now);
            $pendingPolls = $this->processPendingPollVotes($pendingPolls, $room, $now);

            if ($now >= $nextMessageScanAt) {
                [$lastMessageId, $recentMessageIds, $pendingActions] = $this->scanNewMessages(
                    $room,
                    $participants,
                    $botLookup,
                    $lastMessageId,
                    $recentMessageIds,
                    $pendingActions
                );
                $nextMessageScanAt = $now + 0.4;
            }

            if ($now >= $nextReactionScanAt) {
                [$lastReactionId, $pendingActions] = $this->scanNewReactions(
                    $room,
                    $participants,
                    $botLookup,
                    $lastReactionId,
                    $pendingActions
                );
                $nextReactionScanAt = $now + 0.6;
            }

            if ($now >= $nextPollScanAt) {
                [$lastPollId, $pendingPolls] = $this->scanNewPolls(
                    $room,
                    $participants,
                    $lastPollId,
                    $pendingPolls
                );
                $nextPollScanAt = $now + 0.9;
            }

            if ($now >= $nextQuestionScanAt) {
                $pendingRatings = $this->scanAnsweredQuestions($room, $pendingRatings, $now);
                $nextQuestionScanAt = $now + 1.1;
            }

            if ($now >= $nextStreamAt) {
                $message = $this->sendStreamMessage($room, $participants, $recentMessageIds);
                if ($message) {
                    $recentMessageIds = $this->pushRecentMessage($recentMessageIds, $message->id);
                }
                $nextStreamAt = $now + $this->randomDelay($delayMin, $delayMax);
            }

            usleep(200000);
        }
    }

    protected function findRoom(string $input): ?Room
    {
        return Room::where('id', $input)
            ->orWhere('slug', $input)
            ->first();
    }

    protected function loadBotParticipants(Room $room, int $targetCount): Collection
    {
        $participants = Participant::where('room_id', $room->id)
            ->where('session_token', 'like', 'demo_stream_%')
            ->get();

        $missing = max(0, $targetCount - $participants->count());
        if ($missing === 0) {
            return $participants;
        }

        $usedNames = Participant::where('room_id', $room->id)
            ->pluck('display_name')
            ->filter()
            ->values()
            ->all();

        for ($i = 0; $i < $missing; $i++) {
            $name = $this->uniqueUserName($usedNames);
            $usedNames[] = $name;
            $participants->push(Participant::create([
                'room_id' => $room->id,
                'session_token' => 'demo_stream_' . Str::uuid(),
                'display_name' => $name,
            ]));
        }

        return $participants;
    }

    protected function uniqueUserName(array $used): string
    {
        do {
            $name = 'user' . random_int(100, 999);
        } while (in_array($name, $used, true));

        return $name;
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

    protected function randomDelay(float $min, float $max): float
    {
        if ($min <= 0 && $max <= 0) {
            return 0.0;
        }
        $minMs = (int) round($min * 1000);
        $maxMs = (int) round($max * 1000);
        $minMs = max(0, $minMs);
        $maxMs = max($minMs, $maxMs);
        $delayMs = $minMs === $maxMs ? $minMs : random_int($minMs, $maxMs);
        return $delayMs / 1000;
    }

    protected function pushRecentMessage(array $recent, int $messageId, int $limit = 60): array
    {
        $recent[] = $messageId;
        if (count($recent) > $limit) {
            $recent = array_slice($recent, -$limit);
        }
        return $recent;
    }

    protected function scanNewMessages(
        Room $room,
        Collection $participants,
        array $botLookup,
        int $lastMessageId,
        array $recentMessageIds,
        array $pendingActions
    ): array {
        $messages = Message::where('room_id', $room->id)
            ->where('id', '>', $lastMessageId)
            ->orderBy('id')
            ->get();

        foreach ($messages as $message) {
            $lastMessageId = $message->id;
            $recentMessageIds = $this->pushRecentMessage($recentMessageIds, $message->id);

            if ($message->is_system) {
                continue;
            }

            $isBot = $message->participant_id && isset($botLookup[$message->participant_id]);
            if ($isBot) {
                continue;
            }

            $pendingActions = $this->queueRepliesAndReactions(
                $message,
                $participants,
                $pendingActions
            );
        }

        return [$lastMessageId, $recentMessageIds, $pendingActions];
    }

    protected function scanNewReactions(
        Room $room,
        Collection $participants,
        array $botLookup,
        int $lastReactionId,
        array $pendingActions
    ): array {
        $reactions = MessageReaction::whereHas('message', fn ($q) => $q->where('room_id', $room->id))
            ->where('id', '>', $lastReactionId)
            ->orderBy('id')
            ->get();

        foreach ($reactions as $reaction) {
            $lastReactionId = $reaction->id;

            $isBot = $reaction->participant_id && isset($botLookup[$reaction->participant_id]);
            if ($isBot) {
                continue;
            }

            $pendingActions = $this->queueFollowUpReactions(
                $reaction->message_id,
                $participants,
                $pendingActions
            );
        }

        return [$lastReactionId, $pendingActions];
    }

    protected function scanNewPolls(
        Room $room,
        Collection $participants,
        int $lastPollId,
        array $pendingPolls
    ): array {
        $polls = MessagePoll::whereHas('message', fn ($q) => $q->where('room_id', $room->id))
            ->where('id', '>', $lastPollId)
            ->with(['message', 'options'])
            ->orderBy('id')
            ->get();

        foreach ($polls as $poll) {
            $lastPollId = $poll->id;
            if ($poll->is_closed) {
                continue;
            }

            $pendingPolls[$poll->id] = $this->buildPollPlan($poll, $participants);
        }

        return [$lastPollId, $pendingPolls];
    }

    protected function buildPollPlan(MessagePoll $poll, Collection $participants): array
    {
        $participantIds = $participants->pluck('id')->all();
        $existingVotes = MessagePollVote::where('poll_id', $poll->id)
            ->whereNotNull('participant_id')
            ->pluck('participant_id')
            ->all();

        $pending = array_values(array_diff($participantIds, $existingVotes));
        shuffle($pending);

        return [
            'poll_id' => $poll->id,
            'message_id' => $poll->message_id,
            'pending' => $pending,
            'nextVoteAt' => microtime(true) + $this->randomDelay(0.6, 1.6),
        ];
    }

    protected function processPendingPollVotes(array $pendingPolls, Room $room, float $now): array
    {
        foreach ($pendingPolls as $pollId => $plan) {
            if (empty($plan['pending'])) {
                unset($pendingPolls[$pollId]);
                continue;
            }

            if ($now < $plan['nextVoteAt']) {
                continue;
            }

            $poll = MessagePoll::with(['message', 'options'])->find($pollId);
            if (!$poll || $poll->is_closed || !$poll->message || $poll->message->room_id !== $room->id) {
                unset($pendingPolls[$pollId]);
                continue;
            }

            $participantId = array_shift($plan['pending']);
            if ($participantId) {
                $this->castPollVote($room, $poll, $participantId);
            }

            $plan['nextVoteAt'] = $now + $this->randomDelay(0.6, 1.6);
            $pendingPolls[$pollId] = $plan;
        }

        return $pendingPolls;
    }

    protected function scanAnsweredQuestions(Room $room, array $pendingRatings, float $now): array
    {
        $questions = Question::where('room_id', $room->id)
            ->where('status', 'answered')
            ->whereNotNull('answered_at')
            ->whereNotNull('participant_id')
            ->whereDoesntHave('ratings')
            ->orderBy('answered_at')
            ->limit(10)
            ->get();

        foreach ($questions as $question) {
            if (isset($pendingRatings[$question->id])) {
                continue;
            }
            $pendingRatings[$question->id] = [
                'question_id' => $question->id,
                'dueAt' => $now + $this->randomDelay(0.6, 1.8),
            ];
        }

        return $pendingRatings;
    }

    protected function processPendingRatings(array $pendingRatings, float $now): array
    {
        foreach ($pendingRatings as $questionId => $plan) {
            if ($plan['dueAt'] > $now) {
                continue;
            }

            $question = Question::find($questionId);
            if (!$question || $question->status !== 'answered' || !$question->participant_id) {
                unset($pendingRatings[$questionId]);
                continue;
            }

            if ($question->ratings()->exists()) {
                unset($pendingRatings[$questionId]);
                continue;
            }

            $rating = random_int(0, 1) === 1 ? 1 : -1;
            QuestionRating::create([
                'question_id' => $question->id,
                'participant_id' => $question->participant_id,
                'rating' => $rating,
            ]);

            event(new QuestionUpdated($question));
            unset($pendingRatings[$questionId]);
        }

        return $pendingRatings;
    }

    protected function queueRepliesAndReactions(
        Message $message,
        Collection $participants,
        array $pendingActions
    ): array {
        $replyCount = random_int(1, 2);
        $reactionCount = random_int(1, 3);

        $replyActors = $participants->shuffle()->take($replyCount);
        foreach ($replyActors as $participant) {
            $pendingActions[] = [
                'type' => 'reply',
                'message_id' => $message->id,
                'participant_id' => $participant->id,
                'content' => Arr::random($this->replyLines()),
                'dueAt' => microtime(true) + $this->randomDelay(0.4, 1.4),
            ];
        }

        $reactionActors = $participants->shuffle()->take($reactionCount);
        foreach ($reactionActors as $participant) {
            $pendingActions[] = [
                'type' => 'reaction',
                'message_id' => $message->id,
                'participant_id' => $participant->id,
                'emoji' => Arr::random($this->reactionEmojis()),
                'dueAt' => microtime(true) + $this->randomDelay(0.3, 1.2),
            ];
        }

        return $pendingActions;
    }

    protected function queueFollowUpReactions(
        int $messageId,
        Collection $participants,
        array $pendingActions
    ): array {
        $reactionCount = random_int(1, 2);
        $reactionActors = $participants->shuffle()->take($reactionCount);
        foreach ($reactionActors as $participant) {
            $pendingActions[] = [
                'type' => 'reaction',
                'message_id' => $messageId,
                'participant_id' => $participant->id,
                'emoji' => Arr::random($this->reactionEmojis()),
                'dueAt' => microtime(true) + $this->randomDelay(0.4, 1.5),
            ];
        }

        return $pendingActions;
    }

    protected function processPendingActions(array $pendingActions, Room $room, float $now): array
    {
        $remaining = [];

        foreach ($pendingActions as $action) {
            if ($action['dueAt'] > $now) {
                $remaining[] = $action;
                continue;
            }

            if ($action['type'] === 'reply') {
                $target = Message::find($action['message_id']);
                if (!$target) {
                    continue;
                }

                $this->createMessage(
                    room: $room,
                    content: $action['content'],
                    participantId: $action['participant_id'],
                    replyToId: $target->id
                );
                continue;
            }

            if ($action['type'] === 'reaction') {
                $this->addReaction(
                    room: $room,
                    messageId: $action['message_id'],
                    participantId: $action['participant_id'],
                    emoji: $action['emoji']
                );
            }
        }

        return $remaining;
    }

    protected function sendStreamMessage(Room $room, Collection $participants, array $recentMessageIds): ?Message
    {
        $roll = random_int(1, 100);
        $participant = $participants->random();

        if ($roll <= 20) {
            $content = Arr::random($this->questionLines());
            return $this->createMessage(
                room: $room,
                content: $content,
                participantId: $participant->id,
                asQuestion: true
            );
        }

        if ($roll <= 45 && !empty($recentMessageIds)) {
            $targetId = $recentMessageIds[array_rand($recentMessageIds)];
            $target = Message::find($targetId);
            if ($target) {
                $content = Arr::random($this->replyLines());
                return $this->createMessage(
                    room: $room,
                    content: $content,
                    participantId: $participant->id,
                    replyToId: $target->id
                );
            }
        }

        $content = Arr::random($this->messageLines());
        return $this->createMessage(
            room: $room,
            content: $content,
            participantId: $participant->id
        );
    }

    protected function createMessage(
        Room $room,
        string $content,
        ?int $userId = null,
        ?int $participantId = null,
        ?int $replyToId = null,
        bool $asQuestion = false
    ): Message {
        $message = Message::create([
            'room_id' => $room->id,
            'participant_id' => $participantId,
            'user_id' => $userId,
            'reply_to_id' => $replyToId,
            'is_system' => false,
            'content' => $content,
        ]);

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

        $message->loadMissing(['user', 'participant', 'room', 'replyTo.user', 'replyTo.participant', 'reactions', 'poll.options']);
        event(new MessageSent($message));
        if ($question) {
            event(new QuestionCreated($question));
        }

        return $message;
    }

    protected function addReaction(Room $room, int $messageId, int $participantId, string $emoji): void
    {
        MessageReaction::updateOrCreate(
            [
                'message_id' => $messageId,
                'participant_id' => $participantId,
                'user_id' => null,
            ],
            [
                'emoji' => $emoji,
            ]
        );

        $summary = $this->summarizeReactions($messageId);

        event(new ReactionUpdated(
            $room->id,
            $room->slug,
            $messageId,
            $summary,
            [],
            null,
            $participantId
        ));
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
                return strcasecmp((string) $a['emoji'], (string) $b['emoji']);
            })
            ->values()
            ->toArray();
    }

    protected function castPollVote(Room $room, MessagePoll $poll, int $participantId): void
    {
        $poll->loadMissing(['options', 'message']);
        if ($poll->is_closed || !$poll->message || $poll->message->room_id !== $room->id) {
            return;
        }

        if ($poll->options->isEmpty()) {
            return;
        }

        $existing = MessagePollVote::where('poll_id', $poll->id)
            ->where('participant_id', $participantId)
            ->first();

        if ($existing) {
            return;
        }

        $optionId = $poll->options->random()->id;

        MessagePollVote::create([
            'poll_id' => $poll->id,
            'option_id' => $optionId,
            'user_id' => null,
            'participant_id' => $participantId,
        ]);

        $payload = $this->buildPollPayload($poll);

        event(new PollUpdated(
            $room->id,
            $room->slug,
            $poll->message_id,
            $poll->id,
            $payload,
            $optionId,
            null,
            $participantId
        ));
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

    protected function messageLines(): array
    {
        return [
            'Anyone else seeing this issue?',
            'Quick question on the last point.',
            'Thanks for the clarification!',
            'That makes more sense now.',
            'I can share a snippet if needed.',
            'Is the deadline still tomorrow?',
            'Can we recap the main idea?',
            'I tried it and it works now.',
            'Does this work on mobile too?',
            'Nice, that solves it.',
            'I missed the start, what did we cover?',
            'We should test the edge cases too.',
        ];
    }

    protected function replyLines(): array
    {
        return [
            'Good point.',
            'Got it, thanks.',
            'Same here.',
            'That helps a lot.',
            'Let me try that.',
            'I agree with that.',
            'Nice catch.',
            'That sounds right.',
        ];
    }

    protected function questionLines(): array
    {
        return [
            'Question: Could you repeat the steps?',
            'Question: Why does this fail in production?',
            'Question: Is there a simpler alternative?',
            'Question: How should we test this?',
            'Question: What is the expected output?',
            'Question: Any pitfalls to avoid here?',
        ];
    }

    protected function reactionEmojis(): array
    {
        return [
            "👍",
            "👏",
            "🎯",
            "🔥",
            "💡",
            "✅",
            "🤔",
            "🚀",
        ];
    }
}

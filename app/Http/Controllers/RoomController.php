<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\Participant;
use App\Models\RoomBan;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    private const MESSAGE_PAGE_SIZE = 50;
    private const QUEUE_PAGE_SIZE = 50;
    private const MAX_MY_QUESTIONS = 200;
    private static array $identityColumnCache = [];

    public function landing()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('rooms.join');
    }

    public function joinForm()
    {
        return view('rooms.join');
    }

    public function joinSubmit(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $input = trim($data['code']);

        $slug = Str::of($input)
            ->afterLast('/')
            ->before('?')
            ->before('#')
            ->trim()
            ->value();

        if ($slug === '') {
            return back()
                ->withErrors(['code' => 'Enter a valid room code.'])
                ->withInput();
        }

        $room = Room::where('slug', $slug)->first();

        if (!$room) {
            return back()
                ->withErrors(['code' => 'Room not found. Check the code and try again.'])
                ->withInput();
        }

        return redirect()->route('rooms.public', $slug);
    }

    protected function ensureOwner(Room $room): void
    {
        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }
    }

    public function dashboard(Request $request)
    {
        $rooms = $request->user()
            ->rooms()
            ->withCount(['messages', 'questions'])
            ->latest('updated_at')
            ->get();

        return view('dashboard', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public_read' => ['nullable', 'boolean'],
        ]);

        $room = Room::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'slug' => Str::random(10),
            'is_public_read' => $data['is_public_read'] ?? true,
        ]);

        return redirect()
            ->route('rooms.public', $room->slug)
            ->with('status', 'Room created.');
    }

    public function update(Request $request, Room $room)
    {
        $this->ensureOwner($room);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:active,finished'],
        ]);

        $changes = [];

        if (array_key_exists('title', $data)) {
            $changes['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $changes['description'] = $data['description'] ?? null;
        }

        if (array_key_exists('status', $data) && $data['status'] !== $room->status) {
            $changes['status'] = $data['status'];
            $changes['finished_at'] = $data['status'] === 'finished' ? now() : null;
        }

        if (empty($changes)) {
            return back()->with('status', 'No changes applied.');
        }

        $room->update($changes);

        $statusMessage = 'Room updated.';

        if (array_key_exists('status', $changes)) {
            $statusMessage = $changes['status'] === 'finished'
                ? 'Room closed for participants.'
                : 'Room reopened for participants.';
        }

        return back()->with('status', $statusMessage);
    }

    public function destroy(Request $request, Room $room)
    {
        $this->ensureOwner($room);

        $request->validate([
            'confirm_title' => ['required', 'string', Rule::in([$room->title])],
        ], [
            'confirm_title.in' => 'The room name does not match. Type it exactly to delete.',
        ]);

        $room->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Room deleted along with its questions and messages.');
    }

    public function showPublic(Request $request, $slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        if ($room->status === 'finished' && !$room->is_public_read && !$this->isOwner($room)) {
            abort(403);
        }

        $fingerprint = $this->resolveFingerprint($request);
        $ipAddress = $request->ip();

        if (!$this->isOwner($room) && $this->isIdentityBanned($room, $ipAddress, $fingerprint)) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room, $fingerprint, $ipAddress);

        $messagesQuery = $this->applyBannedMessageFilter(
            $this->baseMessagesQuery($room),
            $room,
            $participant
        );
        $messagesCollection = $messagesQuery
            ->limit(self::MESSAGE_PAGE_SIZE + 1)
            ->get();

        $hasMoreMessages = $messagesCollection->count() > self::MESSAGE_PAGE_SIZE;
        $messages = $messagesCollection
            ->take(self::MESSAGE_PAGE_SIZE)
            ->reverse()
            ->values();
        $oldestMessageId = $messages->first()?->id;

        $viewer = $request->user();
        $isOwner = $this->isOwner($room);
        $isBanned = false;

        $queueQuestions = collect();
        $bannedParticipants = collect();
        $queueHasMore = false;
        $queueStatusCounts = [
            'new' => 0,
            'later' => 0,
            'answered' => 0,
            'ignored' => 0,
            'all' => 0,
        ];

        if ($isOwner) {
            $queueCollection = $this->baseQueueQuery($room)
                ->limit(self::QUEUE_PAGE_SIZE + 1)
                ->get();
            $queueHasMore = $queueCollection->count() > self::QUEUE_PAGE_SIZE;
            $queueQuestions = $queueCollection->take(self::QUEUE_PAGE_SIZE);
            $queueStatusCounts = $this->getQueueStatusCounts($room);

            $bannedParticipants = $room->bans()
                ->with('participant')
                ->orderByDesc('created_at')
                ->get();
        } elseif ($participant && $participant->id) {
            $isBanned = $room->isParticipantBanned($participant, $ipAddress, $fingerprint);
        }

        $myQuestions = collect();

        if (!$isOwner && $participant && $participant->id) {
            $myQuestions = $room->questions()
                ->where('participant_id', $participant->id)
                ->whereNull('deleted_by_participant_at')
                ->with(['ratings' => function ($query) use ($participant) {
                    $query->where('participant_id', $participant->id);
                }])
                ->orderBy('created_at', 'desc')
                ->limit(self::MAX_MY_QUESTIONS)
                ->get();
        }

        $reactionPayload = $this->summarizeMessageReactions($messages, $viewer, $participant);
        $pollPayloads = $this->summarizeMessagePolls($messages, $viewer, $participant);

        return view('rooms.show', [
            'room' => $room,
            'messages' => $messages,
            'messagesHasMore' => $hasMoreMessages,
            'messagesOldestId' => $oldestMessageId,
            'participant' => $participant,
            'isOwner' => $isOwner,
            'isBanned' => $isBanned,
            'bannedParticipants' => $bannedParticipants,
            'queueQuestions' => $queueQuestions,
            'queueStatusCounts' => $queueStatusCounts,
            'queueHasMore' => $queueHasMore,
            'queueOffset' => $queueQuestions->count(),
            'myQuestions' => $myQuestions,
            'messagePageSize' => self::MESSAGE_PAGE_SIZE,
            'queuePageSize' => self::QUEUE_PAGE_SIZE,
            'messagesHistoryUrl' => route('rooms.messages.history', $room),
            'queueTotal' => $queueStatusCounts['all'] ?? $queueQuestions->count(),
            'queueInitialCount' => $queueQuestions->count(),
            'queueItemUrlTemplate' => route('rooms.questions.item', [$room, '__QUESTION__']),
            'reactionsByMessage' => $reactionPayload['reactions'],
            'myReactionsByMessage' => $reactionPayload['mine'],
            'pollsByMessage' => $pollPayloads,
        ]);
    }

    public function checkExists(string $slug)
    {
        $exists = Room::where('slug', $slug)->exists();

        return response()->json(['exists' => (bool) $exists]);
    }

    protected function getOrCreateParticipant(Request $request, Room $room, string $fingerprint, ?string $ipAddress = null): Participant
    {
        $user = $request->user();
        $ipAddress = $ipAddress ?? $request->ip();
        $hasIdentityColumns = $this->hasIdentityColumns('participants');

        //      
        if ($user && $user->id === $room->user_id) {
            $data = [
                'room_id' => $room->id,
                'session_token' => '',
                'display_name' => $user->name,
            ];

            if ($hasIdentityColumns) {
                $data['ip_address'] = $ipAddress;
                $data['fingerprint'] = $fingerprint;
            }

            return new Participant($data);
        }

        $sessionKey = 'room_participant_' . $room->id;

        $participantId = $request->session()->get($sessionKey);

        if ($participantId) {
            $participant = Participant::find($participantId);
            if ($participant) {
                if ($user && $user->is_dev && $participant->display_name !== $user->name) {
                    $participant->display_name = $user->name;
                }

                if ($hasIdentityColumns && ($participant->ip_address !== $ipAddress || $participant->fingerprint !== $fingerprint)) {
                    $participant->ip_address = $ipAddress;
                    $participant->fingerprint = $fingerprint;
                }

                if ($participant->isDirty()) {
                    $participant->save();
                }

                return $participant;
            }
        }

        $token = Str::uuid()->toString();

        $participantData = [
            'room_id' => $room->id,
            'session_token' => $token,
            'display_name' => $user && $user->is_dev ? $user->name : 'User' . random_int(1000, 9999),
        ];

        if ($hasIdentityColumns) {
            $participantData['ip_address'] = $ipAddress;
            $participantData['fingerprint'] = $fingerprint;
        }

        $participant = Participant::create($participantData);

        $request->session()->put($sessionKey, $participant->id);

        return $participant;
    }

    public function questionsPanel(Request $request, Room $room)
    {
        $user = auth()->user();
        $isOwner = $user && $user->id === $room->user_id;
        $isAdmin = $user && $user->is_dev;

        if (!$user || (!$isOwner && !$isAdmin)) {
            abort(403);
        }

        $offset = max(0, (int) $request->integer('offset', 0));
        $limit = max(1, min(self::QUEUE_PAGE_SIZE, (int) $request->integer('limit', self::QUEUE_PAGE_SIZE)));

        $status = strtolower((string) $request->get('status', ''));
        $queueQuery = $this->applyQueueStatusFilter($this->baseQueueQuery($room), $status);

        $queueCollection = $queueQuery
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $queueHasMore = $queueCollection->count() > $limit;
        $queueQuestions = $queueCollection->take($limit);
        $queueOffset = $offset + $queueQuestions->count();

        $viewData = [
            'room'            => $room,
            'queueQuestions'  => $queueQuestions,
            'queueStatusCounts' => $this->getQueueStatusCounts($room),
            'isOwner'         => $isOwner,
            'queuePageSize' => self::QUEUE_PAGE_SIZE,
            'queueHasMore' => $queueHasMore,
            'queueOffset' => $queueOffset,
        ];

        if ($request->ajax()) {
            return view('rooms.partials.questions_panel', $viewData);
        }

        return view('rooms.questions_panel_page', $viewData);
    }

    public function questionsChunk(Request $request, Room $room)
    {
        $user = $request->user();
        $isOwner = $user && ($user->id === $room->user_id || $user->is_dev);

        if (!$isOwner) {
            abort(403);
        }

        $offset = max(0, (int) $request->integer('offset', 0));
        $limit = max(1, min(self::QUEUE_PAGE_SIZE, (int) $request->integer('limit', self::QUEUE_PAGE_SIZE)));

        $status = strtolower((string) $request->get('status', ''));
        $queueQuery = $this->applyQueueStatusFilter($this->baseQueueQuery($room), $status);

        $queueCollection = $queueQuery
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $queueCollection->count() > $limit;
        $queueQuestions = $queueCollection->take($limit);
        $nextOffset = $offset + $queueQuestions->count();

        $html = view('rooms.partials.queue_items', [
            'queueQuestions' => $queueQuestions,
            'room' => $room,
            'isOwner' => true,
        ])->render();

        return response()->json([
            'html' => $html,
            'has_more' => $hasMore,
            'next_offset' => $nextOffset,
        ]);
    }

    public function questionItemsBatch(Request $request, Room $room)
    {
        $user = $request->user();
        $isOwner = $user && ($user->id === $room->user_id || $user->is_dev);

        if (!$isOwner) {
            abort(403);
        }

        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), static fn ($value) => $value !== '');
        }

        $ids = collect($ids)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['items' => []]);
        }

        $questions = $this->baseQueueQuery($room)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($ids as $id) {
            $question = $questions->get($id);
            if (!$question) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'html' => view('rooms.partials.queue_item', [
                    'question' => $question,
                    'room' => $room,
                    'isOwner' => true,
                ])->render(),
            ];
        }

        return response()->json(['items' => $items]);
    }

    private function getQueueStatusCounts(Room $room)
    {
        $counts = [
            'new' => 0,
            'later' => 0,
            'answered' => 0,
            'ignored' => 0,
        ];

        $statusCounts = $room->questions()
            ->whereNull('deleted_by_owner_at')
            ->whereNull('deleted_by_participant_at')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        foreach ($statusCounts as $status => $value) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $value;
            }
        }

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    public function myQuestionsPanel(Request $request, Room $room)
    {
        if ($request->user() && $request->user()->id === $room->user_id) {
            abort(403);
        }

        $participant = $this->getOrCreateParticipant($request, $room, $this->resolveFingerprint($request), $request->ip());

        if (!$participant || !$participant->id) {
            abort(403);
        }

        $myQuestions = $room->questions()
            ->where('participant_id', $participant->id)
            ->whereNull('deleted_by_participant_at')
            ->with(['ratings' => function ($query) use ($participant) {
                $query->where('participant_id', $participant->id);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_MY_QUESTIONS)
            ->get();

        return view('rooms.partials.my_questions_panel', [
            'room' => $room,
            'myQuestions' => $myQuestions,
        ]);
    }

    public function questionItem(Request $request, Room $room, Question $question)
    {
        $user = $request->user();
        $isOwner = $user && ($user->id === $room->user_id || $user->is_dev);

        if (!$isOwner || $question->room_id !== $room->id) {
            abort(403);
        }

        $question->load(['participant', 'ratings']);

        return view('rooms.partials.queue_item', [
            'question' => $question,
            'room' => $room,
            'isOwner' => true,
        ]);
    }

    public function messagesHistory(Request $request, Room $room)
    {
        if ($room->status === 'finished' && !$room->is_public_read && !$this->isOwner($room)) {
            abort(403);
        }

        $limit = max(1, min(self::MESSAGE_PAGE_SIZE, (int) $request->integer('limit', self::MESSAGE_PAGE_SIZE)));
        $beforeId = $request->integer('before_id');

        $viewer = $request->user();
        $participant = $this->getOrCreateParticipant($request, $room, $this->resolveFingerprint($request), $request->ip());

        $query = $this->applyBannedMessageFilter(
            $this->baseMessagesQuery($room),
            $room,
            $participant
        );

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $messagesCollection = $query
            ->limit($limit + 1)
            ->get();

        $hasMore = $messagesCollection->count() > $limit;
        $messages = $messagesCollection
            ->take($limit)
            ->reverse()
            ->values();

        $reactionPayload = $this->summarizeMessageReactions($messages, $viewer, $participant);
        $pollPayloads = $this->summarizeMessagePolls($messages, $viewer, $participant);
        $payload = $messages->map(fn (Message $message) => $this->formatMessagePayload(
            $message,
            $participant,
            $viewer,
            $reactionPayload['reactions'],
            $reactionPayload['mine'],
            $pollPayloads,
            $room,
        ));

        return response()->json([
            'data' => $payload,
            'has_more' => $hasMore,
            'next_before_id' => $messages->first()?->id,
        ]);
    }

    protected function baseMessagesQuery(Room $room)
    {
        return $room->messages()
            ->with([
                'participant:id,display_name',
                'user:id,name,is_dev',
                'replyTo' => fn ($query) => $query->select('id', 'user_id', 'participant_id', 'content', 'deleted_at'),
                'replyTo.user:id,name',
                'replyTo.participant:id,display_name',
            ])
            ->withExists(['question as has_question'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    protected function applyBannedMessageFilter(Builder|Relation $query, Room $room, ?Participant $participant = null): Builder|Relation
    {
        $bannedIds = $room->bans()
            ->pluck('participant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($bannedIds->isEmpty()) {
            return $query;
        }

        if ($participant && $participant->id) {
            $viewerId = (int) $participant->id;
            $bannedIds = $bannedIds->reject(fn ($id) => $id === $viewerId)->values();
        }

        if ($bannedIds->isEmpty()) {
            return $query;
        }

        return $query->where(function ($query) use ($bannedIds) {
            $query->whereNull('participant_id')
                ->orWhereNotIn('participant_id', $bannedIds);
        });
    }

    protected function baseQueueQuery(Room $room)
    {
        return $room->questions()
            ->with(['participant', 'ratings'])
            ->whereNull('deleted_by_owner_at')
            ->whereNull('deleted_by_participant_at')
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'later' THEN 1 WHEN 'answered' THEN 2 WHEN 'ignored' THEN 3 ELSE 4 END")
            ->orderBy('created_at');
    }

    protected function applyQueueStatusFilter(Builder|Relation $query, ?string $status): Builder|Relation
    {
        $status = strtolower((string) $status);
        $allowed = ['new', 'later', 'answered', 'ignored'];

        if (!in_array($status, $allowed, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    protected function formatMessagePayload(
        Message $message,
        ?Participant $participant = null,
        ?User $user = null,
        array $reactionsByMessage = [],
        array $myReactionsByMessage = [],
        array $pollPayloads = [],
        ?Room $room = null
    ): array
    {
        $message->loadMissing(['user', 'participant', 'replyTo.user', 'replyTo.participant']);
        $roomModel = $room;
        if (!$roomModel) {
            $message->loadMissing('room');
            $roomModel = $message->room;
        }

        $isOwner = $message->user_id && $roomModel && $roomModel->user_id === $message->user_id;
        $messageId = $message->id;

        $reactions = $reactionsByMessage[$messageId] ?? null;
        $myReactions = $myReactionsByMessage[$messageId] ?? null;

        if ($reactions === null && $message->relationLoaded('reactions')) {
            $reactions = $message->reactions
                ->groupBy('emoji')
                ->map(fn ($items, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $items->count(),
                ])
                ->values()
                ->toArray();
        }

        if ($myReactions === null && $message->relationLoaded('reactions')) {
            $myReactions = $message->reactions
                ->filter(function ($reaction) use ($participant, $user) {
                    if ($user && $reaction->user_id === $user->id) {
                        return true;
                    }
                    if ($participant && $reaction->participant_id === $participant->id) {
                        return true;
                    }
                    return false;
                })
                ->pluck('emoji')
                ->values()
                ->toArray();
        }

        $reactions = $reactions ?? [];
        $myReactions = $myReactions ?? [];
        $poll = $pollPayloads[$messageId] ?? null;

        $replyTo = null;
        if ($message->replyTo) {
            $replyTo = [
                'id' => $message->replyTo->id,
                'author' => $message->replyTo->user_id
                    ? ($message->replyTo->user?->name ?? 'Guest')
                    : ($message->replyTo->participant?->display_name ?? 'Guest'),
                'content' => $message->replyTo->trashed()
                    ? 'Message deleted'
                    : Str::limit($message->replyTo->content, 140),
                'is_deleted' => $message->replyTo->trashed(),
            ];
        }

        return [
            'id' => $message->id,
            'room_id' => $message->room_id,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
            'author' => [
                'type' => $isOwner ? 'owner' : 'participant',
                'name' => $message->user_id
                    ? ($message->user?->name ?? 'Guest')
                    : ($message->participant?->display_name ?? 'Guest'),
                'user_id' => $message->user_id,
                'participant_id' => $message->participant_id,
                'is_dev' => (bool) $message->user?->is_dev,
                'is_owner' => $isOwner,
            ],
            'as_question' => (bool) ($message->has_question ?? ($message->relationLoaded('question') ? $message->question : $message->question()->exists())),
            'reply_to' => $replyTo,
            'reactions' => $reactions,
            'myReactions' => $myReactions,
            'poll' => $poll,
        ];
    }

    protected function summarizeMessageReactions(Collection $messages, ?User $user = null, ?Participant $participant = null): array
    {
        $messageIds = $messages->pluck('id')->filter()->values();
        if ($messageIds->isEmpty()) {
            return [
                'reactions' => [],
                'mine' => [],
            ];
        }

        $summaryRows = MessageReaction::query()
            ->select('message_id', 'emoji')
            ->whereIn('message_id', $messageIds)
            ->get();

        $reactionsByMessage = $summaryRows
            ->groupBy('message_id')
            ->map(function ($items) {
                return $items
                    ->groupBy('emoji')
                    ->map(fn ($group, $emoji) => [
                        'emoji' => $emoji,
                        'count' => $group->count(),
                    ])
                    ->sort(function ($a, $b) {
                        $countDiff = $b['count'] <=> $a['count'];
                        if ($countDiff !== 0) {
                            return $countDiff;
                        }
                        return strcasecmp($a['emoji'], $b['emoji']);
                    })
                    ->values()
                    ->toArray();
            })
            ->toArray();

        $myReactionsByMessage = [];
        $userId = $user?->id;
        $participantId = $participant?->id;

        if ($userId || $participantId) {
            $mineRows = MessageReaction::query()
                ->select('message_id', 'emoji')
                ->whereIn('message_id', $messageIds)
                ->where(function ($query) use ($userId, $participantId) {
                    if ($userId) {
                        $query->orWhere('user_id', $userId);
                    }
                    if ($participantId) {
                        $query->orWhere('participant_id', $participantId);
                    }
                })
                ->get();

            $myReactionsByMessage = $mineRows
                ->groupBy('message_id')
                ->map(fn ($items) => $items->pluck('emoji')->values()->toArray())
                ->toArray();
        }

        return [
            'reactions' => $reactionsByMessage,
            'mine' => $myReactionsByMessage,
        ];
    }

    protected function summarizeMessagePolls(Collection $messages, ?User $user = null, ?Participant $participant = null): array
    {
        $messageIds = $messages->pluck('id')->filter()->values();
        if ($messageIds->isEmpty()) {
            return [];
        }

        $polls = MessagePoll::query()
            ->with('options')
            ->whereIn('message_id', $messageIds)
            ->get();

        if ($polls->isEmpty()) {
            return [];
        }

        $pollIds = $polls->pluck('id')->values();
        $voteRows = MessagePollVote::query()
            ->select('poll_id', 'option_id')
            ->whereIn('poll_id', $pollIds)
            ->get();

        $voteCounts = $voteRows
            ->groupBy('poll_id')
            ->map(fn ($items) => $items->groupBy('option_id')->map->count());

        $myVotes = collect();
        $userId = $user?->id;
        $participantId = $participant?->id;

        if ($userId || $participantId) {
            $myVotes = MessagePollVote::query()
                ->select('poll_id', 'option_id')
                ->whereIn('poll_id', $pollIds)
                ->where(function ($query) use ($userId, $participantId) {
                    if ($userId) {
                        $query->orWhere('user_id', $userId);
                    }
                    if ($participantId) {
                        $query->orWhere('participant_id', $participantId);
                    }
                })
                ->get()
                ->keyBy('poll_id');
        }

        $payloads = [];
        foreach ($polls as $poll) {
            $counts = $voteCounts->get($poll->id, collect());
            $myVote = $myVotes->get($poll->id);
            $payloads[$poll->message_id] = $this->buildPollPayload(
                $poll,
                $counts,
                $myVote?->option_id
            );
        }

        return $payloads;
    }

    protected function buildPollPayload(MessagePoll $poll, $counts, ?int $myVoteOptionId = null): array
    {
        $countMap = collect($counts)->map(fn ($count) => (int) $count);
        $totalVotes = $countMap->sum();

        $options = $poll->options
            ->sortBy('position')
            ->map(function ($option) use ($countMap, $totalVotes) {
                $votes = (int) ($countMap->get($option->id, 0));
                $percent = $totalVotes > 0 ? (int) round(($votes / $totalVotes) * 100) : 0;
                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => $votes,
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
            'my_vote_id' => $myVoteOptionId,
            'is_closed' => (bool) $poll->is_closed,
        ];
    }

    protected function resolveFingerprint(Request $request): string
    {
        $existing = $request->cookie('lc_fp');
        if ($existing) {
            return $existing;
        }

        $fingerprint = (string) Str::uuid();
        $secure = (bool) config('session.secure', $request->isSecure());
        $sameSite = config('session.same_site', 'lax') ?: 'lax';
        $domain = config('session.domain');

        Cookie::queue(cookie(
            'lc_fp',
            $fingerprint,
            60 * 24 * 365,
            '/',
            $domain,
            $secure,
            true,
            false,
            $sameSite
        ));

        return $fingerprint;
    }

    protected function isIdentityBanned(Room $room, ?string $ipAddress, ?string $fingerprint): bool
    {
        $hasIdentityColumns = $this->hasIdentityColumns('room_bans');

        if (!$hasIdentityColumns) {
            return false;
        }

        if (!$ipAddress && !$fingerprint) {
            return false;
        }

        return $room->bans()
            ->where(function ($query) use ($ipAddress, $fingerprint) {
                if ($ipAddress) {
                    $query->orWhere('ip_address', $ipAddress);
                }
                if ($fingerprint) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->exists();
    }

    protected function isOwner(Room $room): bool
    {
        return auth()->check() && auth()->id() === $room->user_id;
    }

    protected function hasIdentityColumns(string $table): bool
    {
        if (array_key_exists($table, self::$identityColumnCache)) {
            return self::$identityColumnCache[$table];
        }

        return self::$identityColumnCache[$table] = Schema::hasColumns($table, ['ip_address', 'fingerprint']);
    }
}

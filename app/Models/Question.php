<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\Message|null $message
 * @property-read \App\Models\Participant|null $participant
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuestionRating> $ratings
 */
class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'message_id',
        'participant_id',
        'user_id',
        'content',
        'status',
        'answered_at',
        'ignored_at',
        'deleted_by_participant_at',
        'deleted_by_owner_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'ignored_at' => 'datetime',
        'deleted_by_participant_at' => 'datetime',
        'deleted_by_owner_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(QuestionRating::class);
    }
}

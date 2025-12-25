<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagePoll extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'question',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'bool',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(MessagePollOption::class, 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(MessagePollVote::class, 'poll_id');
    }
}

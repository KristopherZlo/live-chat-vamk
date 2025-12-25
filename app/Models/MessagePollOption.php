<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property-read \App\Models\MessagePoll|null $poll
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MessagePollVote> $votes
 */
class MessagePollOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'label',
        'position',
    ];

    public function poll()
    {
        return $this->belongsTo(MessagePoll::class, 'poll_id');
    }

    public function votes()
    {
        return $this->hasMany(MessagePollVote::class, 'option_id');
    }
}

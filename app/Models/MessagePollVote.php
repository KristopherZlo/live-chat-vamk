<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessagePollVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'option_id',
        'user_id',
        'participant_id',
    ];

    public function poll()
    {
        return $this->belongsTo(MessagePoll::class, 'poll_id');
    }

    public function option()
    {
        return $this->belongsTo(MessagePollOption::class, 'option_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}

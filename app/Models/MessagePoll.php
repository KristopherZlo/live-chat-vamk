<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function options()
    {
        return $this->hasMany(MessagePollOption::class, 'poll_id');
    }

    public function votes()
    {
        return $this->hasMany(MessagePollVote::class, 'poll_id');
    }
}

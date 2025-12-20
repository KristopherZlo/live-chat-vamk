<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

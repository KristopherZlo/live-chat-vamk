<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'participant_id',
        'reply_to_id',
        'user_id',
        'is_system',
        'content',
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function question()
    {
        return $this->hasOne(Question::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'participant_id',
        'reply_to_id',
        'user_id',
        'deleted_by_user_id',
        'deleted_by_participant_id',
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
        return $this->belongsTo(Message::class, 'reply_to_id')->withTrashed();
    }

    public function question()
    {
        return $this->hasOne(Question::class);
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function deletedByParticipant()
    {
        return $this->belongsTo(Participant::class, 'deleted_by_participant_id');
    }
}

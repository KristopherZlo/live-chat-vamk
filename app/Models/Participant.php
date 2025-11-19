<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'session_token',
        'display_name',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}

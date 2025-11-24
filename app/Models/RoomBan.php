<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'participant_id',
        'session_token',
        'display_name',
        'ip_address',
        'fingerprint',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}

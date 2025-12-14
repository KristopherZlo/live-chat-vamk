<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\RoomBan;
use App\Models\Participant;
use Illuminate\Support\Facades\Schema;

class Room extends Model
{
    use HasFactory;

    private static ?bool $banIdentityColumns = null;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'status',
        'is_public_read',
        'finished_at',
    ];

    protected $casts = [
        'is_public_read' => 'bool',
        'finished_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function bans()
    {
        return $this->hasMany(RoomBan::class);
    }

    public function isParticipantBanned(?Participant $participant, ?string $ipAddress = null, ?string $fingerprint = null): bool
    {
        if (!$participant) {
            return false;
        }

        if (self::$banIdentityColumns === null) {
            self::$banIdentityColumns = Schema::hasColumn('room_bans', 'ip_address')
                && Schema::hasColumn('room_bans', 'fingerprint');
        }

        return $this->bans()
            ->where(function ($query) use ($participant, $ipAddress, $fingerprint) {
                $query->where('participant_id', $participant->id)
                    ->orWhere('session_token', $participant->session_token);

                if (self::$banIdentityColumns) {
                    if ($ipAddress) {
                        $query->orWhere('ip_address', $ipAddress);
                    }

                    if ($fingerprint) {
                        $query->orWhere('fingerprint', $fingerprint);
                    }
                }
            })
            ->exists();
    }
}

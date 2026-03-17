<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Room extends Model
{
    use HasFactory;

    private static ?bool $banIdentityColumns = null;

    public const CARD_COLORS = [
        'ocean',
        'mint',
        'amber',
        'rose',
        'violet',
        'teal',
        'slate',
        'coral',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'status',
        'card_color',
        'sort_order',
        'is_public_read',
        'finished_at',
    ];

    protected $casts = [
        'is_public_read' => 'bool',
        'sort_order' => 'int',
        'finished_at' => 'datetime',
    ];

    public static function canAccessHostChannel(User $user, string $slug): bool
    {
        $room = static::query()
            ->select(['id', 'user_id'])
            ->where('slug', $slug)
            ->first();

        return (bool) ($room
            && ((int) $room->user_id === (int) $user->id || (bool) $user->is_dev));
    }

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

    public function isIdentityBanned(?string $ipAddress = null, ?string $fingerprint = null): bool
    {
        if (! $this->hasBanIdentityColumns()) {
            return false;
        }

        if (! $ipAddress && ! $fingerprint) {
            return false;
        }

        return $this->bans()
            ->where(function ($query) use ($ipAddress, $fingerprint) {
                if ($ipAddress) {
                    $query->orWhere('ip_address', $ipAddress);
                }

                if ($fingerprint) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->exists();
    }

    public function isParticipantBanned(?Participant $participant, ?string $ipAddress = null, ?string $fingerprint = null): bool
    {
        if (! $participant) {
            return false;
        }

        $hasIdentityColumns = $this->hasBanIdentityColumns();

        return $this->bans()
            ->where(function ($query) use ($participant, $ipAddress, $fingerprint, $hasIdentityColumns) {
                $query->where('participant_id', $participant->id)
                    ->orWhere('session_token', $participant->session_token);

                if ($hasIdentityColumns) {
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

    public function isAccessRevoked(?Participant $participant = null, ?string $ipAddress = null, ?string $fingerprint = null): bool
    {
        return $this->isIdentityBanned($ipAddress, $fingerprint)
            || $this->isParticipantBanned($participant, $ipAddress, $fingerprint);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function hasBanIdentityColumns(): bool
    {
        if (self::$banIdentityColumns === null) {
            self::$banIdentityColumns = Schema::hasColumn('room_bans', 'ip_address')
                && Schema::hasColumn('room_bans', 'fingerprint');
        }

        return self::$banIdentityColumns;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'action',
        'actor_user_id',
        'actor_participant_id',
        'room_id',
        'target_type',
        'target_id',
        'ip_address',
        'request_id',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorParticipant()
    {
        return $this->belongsTo(Participant::class, 'actor_participant_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Record a structured audit entry for the current request.
     */
    public static function record(Request $request, string $action, array $context = []): void
    {
        $requestId = $request->headers->get('X-Request-Id');
        $requestId = is_string($requestId) && $requestId !== '' ? $requestId : null;

        $metadata = $context['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = ['value' => $metadata];
        }

        $userAgent = $request->userAgent();
        $userAgent = $userAgent ? Str::limit($userAgent, 512, '') : null;

        try {
            self::create([
                'action' => $action,
                'actor_user_id' => $context['actor_user_id'] ?? $request->user()?->id,
                'actor_participant_id' => $context['actor_participant_id'] ?? null,
                'room_id' => $context['room_id'] ?? null,
                'target_type' => $context['target_type'] ?? null,
                'target_id' => $context['target_id'] ?? null,
                'ip_address' => $request->ip(),
                'request_id' => $requestId,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log write failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UnverifiedUserCleanupService
{
    public function pruneStaleUsers(): int
    {
        $ttlHours = max(1, (int) config('ghostroom.auth.unverified_user_ttl_hours', 24));
        $now = now();

        return $this->sameDayUnverifiedUsers($now)
            ->where('created_at', '<', $now->copy()->subHours($ttlHours))
            ->delete();
    }

    public function countPendingUsersForIp(string $registrationIp): int
    {
        return $this->sameDayUnverifiedUsers(now())
            ->where('registration_ip', $registrationIp)
            ->count();
    }

    protected function sameDayUnverifiedUsers(Carbon $now): Builder
    {
        return User::query()
            ->whereNull('email_verified_at')
            // Legacy accounts created before today must never be auto-removed.
            ->where('created_at', '>=', $now->copy()->startOfDay());
    }
}

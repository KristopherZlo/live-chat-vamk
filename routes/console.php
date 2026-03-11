<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app()->booted(function () {
    $schedule = app(Schedule::class);

    $schedule->command('observability:prune')
        ->dailyAt('02:30')
        ->withoutOverlapping();

    $schedule->command('observability:check')
        ->everyFiveMinutes()
        ->withoutOverlapping();

    $schedule->call(function (): void {
        $ttlHours = max(1, (int) config('ghostroom.auth.unverified_user_ttl_hours', 24));

        User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($ttlHours))
            ->delete();
    })->hourly()
        ->name('auth:prune-unverified-users')
        ->withoutOverlapping();
});

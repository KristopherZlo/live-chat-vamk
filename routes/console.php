<?php

use App\Services\Auth\UnverifiedUserCleanupService;
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
        app(UnverifiedUserCleanupService::class)->pruneStaleUsers();
    })->hourly()
        ->name('auth:prune-unverified-users')
        ->withoutOverlapping();
});

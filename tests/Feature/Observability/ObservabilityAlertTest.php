<?php

use App\Models\ClientErrorReport;
use Illuminate\Support\Facades\Cache;

test('observability check emits client error spike alert with cooldown', function () {
    Cache::flush();

    config()->set('observability.alerts.enabled', true);
    config()->set('observability.alerts.log_channel', 'stack');
    config()->set('observability.alerts.cooldown_minutes', 30);
    config()->set('observability.alerts.client_errors.threshold', 2);
    config()->set('observability.alerts.client_errors.window_minutes', 5);
    config()->set('observability.alerts.audit.threshold', 1000);

    ClientErrorReport::create([
        'severity' => 'error',
        'message' => 'Client error one',
        'url' => 'https://example.test/one',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);
    ClientErrorReport::create([
        'severity' => 'error',
        'message' => 'Client error two',
        'url' => 'https://example.test/two',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->artisan('observability:check')
        ->assertExitCode(0);

    expect(Cache::has('observability:alert:client_errors'))->toBeTrue();
    $firstValue = Cache::get('observability:alert:client_errors');

    $this->artisan('observability:check')
        ->assertExitCode(0);

    expect(Cache::get('observability:alert:client_errors'))->toBe($firstValue);
});

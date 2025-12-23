<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

test('client error reports are stored', function () {
    $response = $this->postJson(route('client-errors.store'), [
        'message' => 'Client error',
        'url' => 'https://example.test/page',
        'severity' => 'error',
        'stack' => 'Error: Client error',
        'line' => 42,
        'column' => 7,
        'metadata' => [
            'page_request_id' => 'req-123',
        ],
    ]);

    $response->assertStatus(201)->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('client_error_reports', [
        'message' => 'Client error',
        'severity' => 'error',
        'url' => 'https://example.test/page',
    ]);
});

test('client error reports validate required fields', function () {
    $response = $this->postJson(route('client-errors.store'), [
        'url' => 'https://example.test/page',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('message');
});

test('client error reports are rate limited', function () {
    RateLimiter::for('client-errors', function (Request $request) {
        return Limit::perMinute(2)->by($request->ip());
    });

    $payload = [
        'message' => 'Throttled error',
        'url' => 'https://example.test/page',
    ];

    for ($i = 0; $i < 2; $i++) {
        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->postJson(route('client-errors.store'), $payload)
            ->assertStatus(201);
    }

    $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->postJson(route('client-errors.store'), $payload)
        ->assertStatus(429);
});

<?php

test('room join is rate limited per code and ip', function () {
    $ip = '10.10.10.1';
    $code = 'missing-room-code';

    for ($i = 0; $i < 5; $i++) {
        $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/join', ['code' => $code])
            ->assertStatus(302);
    }

    $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/join', ['code' => $code])
        ->assertStatus(429);
});

test('room exists endpoint is rate limited per slug and ip', function () {
    $ip = '10.10.10.2';
    $slug = 'missing-room-slug';

    for ($i = 0; $i < 15; $i++) {
        $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->get("/rooms/{$slug}/exists")
            ->assertStatus(200);
    }

    $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->get("/rooms/{$slug}/exists")
        ->assertStatus(429);
});

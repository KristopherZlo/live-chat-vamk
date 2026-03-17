<?php

use App\Models\Room;
use App\Models\User;
use DOMDocument;
use Illuminate\Support\Str;

test('security headers are applied to web responses', function () {
    $response = $this->get('/join');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), fullscreen=(self "https://www.youtube.com" "https://www.youtube-nocookie.com"), payment=()');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("frame-ancestors 'self'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->toContain("base-uri 'self'");
    expect($csp)->toContain("frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com");
    expect($csp)->toContain("form-action 'self'");
    expect($csp)->not->toContain("'unsafe-inline'");
    expect($csp)->not->toContain('cdn.jsdelivr.net');
});

test('hsts header is added for secure requests', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->get('/join');

    $response->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
});

test('room page does not ship runtime cdn assets or inline room config scripts', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Open beta room',
        'slug' => 'room-'.Str::random(8),
    ]);

    $response = $this->get(route('rooms.public', $room));

    $response->assertOk();
    $response->assertDontSee('cdn.jsdelivr.net', false);
    $response->assertDontSee('<script type="application/json" id="roomPageConfig">', false);
    $response->assertSee('data-room-page-config=', false);
    $response->assertDontSee('<emoji-picker id="chatEmojiPicker"', false);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    libxml_clear_errors();

    $configNode = $dom->getElementById('roomPageConfig');
    expect($configNode)->not->toBeNull();

    $config = json_decode($configNode->getAttribute('data-room-page-config'), true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($config)->toBeArray();
    expect($config['roomSlug'] ?? null)->toBe($room->slug);
});

test('room page csp keeps realtime origins but no runtime cdn allowances', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'CSP room',
        'slug' => 'room-'.Str::random(8),
    ]);

    $response = $this->get(route('rooms.public', $room));
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("connect-src 'self'");
    expect($csp)->toContain('script-src \'self\'');
    expect($csp)->not->toContain('cdn.jsdelivr.net');
});

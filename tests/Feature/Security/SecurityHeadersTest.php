<?php

test('security headers are applied to web responses', function () {
    $response = $this->get('/join');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), fullscreen=(self), payment=()');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("frame-ancestors 'self'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->toContain("base-uri 'self'");
    expect($csp)->toContain("frame-src 'none'");
    expect($csp)->toContain("form-action 'self'");
});

test('hsts header is added for secure requests', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->get('/join');

    $response->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains; preload');
});

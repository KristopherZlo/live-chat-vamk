<?php

test('responses include a request id header', function () {
    $response = $this->get('/join');

    $response->assertHeader('X-Request-Id');
    $requestId = $response->headers->get('X-Request-Id');
    expect($requestId)->not->toBeEmpty();
});

test('client request id is preserved', function () {
    $response = $this->withHeaders([
        'X-Request-Id' => 'client-id-123',
    ])->get('/join');

    $response->assertHeader('X-Request-Id', 'client-id-123');
});

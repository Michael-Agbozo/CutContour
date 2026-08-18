<?php

test('security response headers are present on web responses', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
});

test('HSTS header is absent outside production', function () {
    app()->detectEnvironment(fn () => 'testing');

    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('HSTS header is present in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

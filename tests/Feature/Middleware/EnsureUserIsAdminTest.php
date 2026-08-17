<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('middleware aborts 403 for guest requests', function () {
    $request = Request::create('/admin');

    $middleware = new EnsureUserIsAdmin;

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

test('middleware aborts 403 for non-admin authenticated users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $request = Request::create('/admin');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsAdmin;

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

test('middleware passes through admin authenticated users', function () {
    $user = User::factory()->admin()->create();
    $request = Request::create('/admin');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserIsAdmin;

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});

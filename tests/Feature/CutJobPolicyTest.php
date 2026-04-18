<?php

use App\Models\CutJob;
use App\Models\User;

test('user can view their own job', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create();

    expect($user->can('view', $job))->toBeTrue();
});

test('user cannot view another users job', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($other)->create();

    expect($user->can('view', $job))->toBeFalse();
});

test('user can create jobs', function () {
    $user = User::factory()->create();

    expect($user->can('create', CutJob::class))->toBeTrue();
});

test('user can view any of their own jobs', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', CutJob::class))->toBeTrue();
});

test('user can download a completed job they own', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    expect($user->can('download', $job))->toBeTrue();
});

test('user cannot download an incomplete job', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create(); // status: processing

    expect($user->can('download', $job))->toBeFalse();
});

test('user cannot download another users completed job', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($other)->completed()->create();

    expect($user->can('download', $job))->toBeFalse();
});

test('user can delete their own job', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create();

    expect($user->can('delete', $job))->toBeTrue();
});

test('user cannot delete another users job', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($other)->create();

    expect($user->can('delete', $job))->toBeFalse();
});

test('visible scope excludes expired jobs', function () {
    $user = User::factory()->create();
    CutJob::factory()->for($user)->create(['status' => 'processing']);
    CutJob::factory()->for($user)->completed()->create();
    CutJob::factory()->for($user)->expired()->create();

    $visible = CutJob::visible()->get();

    expect($visible)->toHaveCount(2)
        ->and($visible->pluck('status')->toArray())->not->toContain('expired');
});

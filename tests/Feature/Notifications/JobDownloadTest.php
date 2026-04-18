<?php

use App\Models\CutJob;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake();
});

test('authenticated user can download their completed job with a valid signed url', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create([
        'output_path' => 'users/'.$user->id.'/jobs/1/output.pdf',
        'width' => 800,
        'height' => 600,
    ]);

    Storage::put($job->output_path, 'PDF content');

    $url = URL::temporarySignedRoute('jobs.download', now()->addDays(7), ['cutJob' => $job->id]);

    $this->actingAs($user)->get($url)->assertOk();
});

test('download is denied for a job owned by another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($owner)->completed()->create([
        'output_path' => 'users/'.$owner->id.'/jobs/1/output.pdf',
    ]);

    Storage::put($job->output_path, 'PDF content');

    $url = URL::temporarySignedRoute('jobs.download', now()->addDays(7), ['cutJob' => $job->id]);

    $this->actingAs($other)->get($url)->assertForbidden();
});

test('download returns 404 for an incomplete job', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create(['status' => 'processing', 'output_path' => null]);

    $url = URL::temporarySignedRoute('jobs.download', now()->addDays(7), ['cutJob' => $job->id]);

    $this->actingAs($user)->get($url)->assertForbidden();
});

test('expired signed url is rejected', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create([
        'output_path' => 'users/'.$user->id.'/jobs/1/output.pdf',
    ]);

    $url = URL::temporarySignedRoute('jobs.download', now()->subSecond(), ['cutJob' => $job->id]);

    $this->actingAs($user)->get($url)->assertForbidden();
});

test('unsigned url is rejected', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->get(route('jobs.download', $job))
        ->assertForbidden();
});

test('unauthenticated request redirects to login', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $url = URL::temporarySignedRoute('jobs.download', now()->addDays(7), ['cutJob' => $job->id]);

    $this->get($url)->assertRedirectToRoute('login');
});

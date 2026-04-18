<?php

use App\Models\CutJob;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('cleanup marks expired jobs as expired and nulls file paths', function () {
    Storage::fake();

    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'status' => 'completed',
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('expired')
        ->and($job->file_path)->toBeNull()
        ->and($job->output_path)->toBeNull();
});

test('cleanup does not touch jobs that have not expired', function () {
    Storage::fake();

    $user = User::factory()->create();
    $active = CutJob::factory()->for($user)->completed()->create([
        'expires_at' => now()->addDays(89),
    ]);

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    $active->refresh();

    expect($active->status)->toBe('completed');
});

test('cleanup does not re-process already expired jobs', function () {
    Storage::fake();

    $user = User::factory()->create();
    $alreadyExpired = CutJob::factory()->for($user)->expired()->create();

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    $alreadyExpired->refresh();
    expect($alreadyExpired->status)->toBe('expired');
});

test('cleanup deletes the job directory from storage', function () {
    Storage::fake();

    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->create([
        'status' => 'completed',
        'expires_at' => now()->subDay(),
    ]);

    $dir = "users/{$user->id}/jobs/{$job->id}";
    Storage::makeDirectory($dir);
    Storage::put("{$dir}/original.png", 'fake-content');
    Storage::put("{$dir}/output.pdf", 'fake-pdf');

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    expect(Storage::exists($dir))->toBeFalse();
});

test('cleanup purges failed jobs older than retention hours', function () {
    Storage::fake();

    $user = User::factory()->create();
    $oldFailed = CutJob::factory()->for($user)->failed()->create([
        'created_at' => now()->subHours(4),
    ]);

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    $oldFailed->refresh();

    expect($oldFailed->status)->toBe('expired')
        ->and($oldFailed->file_path)->toBeNull()
        ->and($oldFailed->output_path)->toBeNull();
});

test('cleanup does not purge recent failed jobs', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recentFailed = CutJob::factory()->for($user)->failed()->create([
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('cutjob:cleanup')->assertSuccessful();

    $recentFailed->refresh();

    expect($recentFailed->status)->toBe('failed');
});

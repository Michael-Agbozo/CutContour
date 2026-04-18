<?php

use App\Models\CutJob;
use App\Models\User;
use App\Notifications\CutJobNotification;
use Illuminate\Support\Facades\Notification;

test('completed job notification uses database and mail channels', function () {
    $job = CutJob::factory()->for(User::factory()->create())->completed()->create();
    $notification = new CutJobNotification($job, 'completed');

    expect($notification->via(new stdClass))->toBe(['database', 'mail']);
});

test('failed job notification uses database channel only', function () {
    $job = CutJob::factory()->for(User::factory()->create())->create(['status' => 'failed']);
    $notification = new CutJobNotification($job, 'failed');

    expect($notification->via(new stdClass))->toBe(['database']);
});

test('completed notification database payload includes download_url', function () {
    $job = CutJob::factory()->for(User::factory()->create())->completed()->create();
    $notification = new CutJobNotification($job, 'completed');

    $payload = $notification->toDatabase(new stdClass);

    expect($payload['status'])->toBe('completed')
        ->and($payload['cut_job_id'])->toBe($job->id)
        ->and($payload['original_name'])->toBe($job->original_name)
        ->and($payload['download_url'])->toBeString()->toContain('jobs/'.$job->id.'/download');
});

test('failed notification database payload includes error_message and no download_url', function () {
    $job = CutJob::factory()->for(User::factory()->create())->create([
        'status' => 'failed',
        'error_message' => 'Vectorization failed',
    ]);
    $notification = new CutJobNotification($job, 'failed');

    $payload = $notification->toDatabase(new stdClass);

    expect($payload['status'])->toBe('failed')
        ->and($payload['download_url'])->toBeNull()
        ->and($payload['error_message'])->toBe('Vectorization failed');
});

test('completed job mail notification contains file name in subject', function () {
    $job = CutJob::factory()->for(User::factory()->create())->completed()->create();
    $notification = new CutJobNotification($job, 'completed');
    $notifiable = User::factory()->make();

    $mail = $notification->toMail($notifiable);

    expect($mail->subject)->toContain($job->original_name)
        ->and($mail->actionText)->toBe('Download PDF');
});

test('notification is dispatched when job completes', function () {
    Notification::fake();

    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));

    Notification::assertSentTo($user, CutJobNotification::class, function ($notification) {
        return $notification->status === 'completed';
    });
});

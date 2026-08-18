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

test('completed notification database payload stores only cut_job_id, never a signed url', function () {
    $job = CutJob::factory()->for(User::factory()->create())->completed()->create();
    $notification = new CutJobNotification($job, 'completed');

    $payload = $notification->toDatabase(new stdClass);

    // The DB payload MUST NOT contain a signed URL — consumers rebuild the URL
    // at render time from cut_job_id so notifications don't 403 after the TTL,
    // and a leaked notifications table can't yield working download links.
    expect($payload)->not->toHaveKey('download_url')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['cut_job_id'])->toBe($job->id)
        ->and($payload['original_name'])->toBe($job->original_name);
});

test('failed notification database payload includes error_message and no download url', function () {
    $job = CutJob::factory()->for(User::factory()->create())->create([
        'status' => 'failed',
        'error_message' => 'Vectorization failed',
    ]);
    $notification = new CutJobNotification($job, 'failed');

    $payload = $notification->toDatabase(new stdClass);

    expect($payload['status'])->toBe('failed')
        ->and($payload)->not->toHaveKey('download_url')
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

test('database payload does not store any signed download url', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $payload = (new CutJobNotification($job, 'completed'))->toDatabase($user);

    // Consumers rebuild the URL at render time from cut_job_id — see
    // notification-bell.blade.php and notifications/⚡index.blade.php.
    expect($payload)->not->toHaveKey('download_url')
        ->and($payload)->toHaveKey('cut_job_id');
});

test('mail action url keeps the long 7-day TTL', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $before = now();
    $notification = new CutJobNotification($job, 'completed');
    $mail = $notification->toMail($user);

    parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    $expiresAt = (int) $query['expires'];
    // Should be well past the 15-minute in-app TTL (at least 6 days out).
    expect($expiresAt)->toBeGreaterThan($before->copy()->addDays(6)->getTimestamp());
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

<?php

use App\Models\CutJob;
use App\Models\User;
use App\Notifications\CutJobNotification;

test('markAllNotificationsRead clears every unread notification for the user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $job = CutJob::factory()->for($user)->completed()->create();
    $otherJob = CutJob::factory()->for($other)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));
    $user->notify(new CutJobNotification($job, 'failed'));
    $other->notify(new CutJobNotification($otherJob, 'completed'));

    expect($user->unreadNotifications()->count())->toBe(2)
        ->and($other->unreadNotifications()->count())->toBe(1);

    $marked = $user->markAllNotificationsRead();

    expect($marked)->toBe(2)
        ->and($user->fresh()->unreadNotifications()->count())->toBe(0)
        // Other user's unread state must be untouched.
        ->and($other->fresh()->unreadNotifications()->count())->toBe(1);
});

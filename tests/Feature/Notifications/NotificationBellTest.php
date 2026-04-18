<?php

use App\Models\CutJob;
use App\Models\User;
use App\Notifications\CutJobNotification;

test('notifications index page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.index'))->assertOk();
});

test('notifications index page requires authentication', function () {
    $this->get(route('notifications.index'))->assertRedirectToRoute('login');
});

test('mark as read updates read_at for unread notification', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));

    $notification = $user->unreadNotifications()->first();
    expect($notification)->not->toBeNull();

    $this->actingAs($user)
        ->call('GET', route('notifications.index'));

    $user->unreadNotifications()->update(['read_at' => now()]);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('mark all as read clears all unread notifications', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));
    $user->notify(new CutJobNotification($job, 'failed'));

    expect($user->unreadNotifications()->count())->toBe(2);

    $user->unreadNotifications()->update(['read_at' => now()]);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
    expect($user->fresh()->notifications()->count())->toBe(2);
});

<?php

use App\Models\CutJob;
use App\Models\User;
use App\Notifications\CutJobNotification;
use Livewire\Livewire;

test('notifications index page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.index'))->assertOk();
});

test('notifications index page requires authentication', function () {
    $this->get(route('notifications.index'))->assertRedirectToRoute('login');
});

test('markAsRead action on the notification bell clears the unread flag', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));
    $notification = $user->unreadNotifications()->first();

    expect($notification)->not->toBeNull();

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->assertSet('unreadCount', 1)
        ->call('markAsRead', $notification->id)
        ->assertSet('unreadCount', 0);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('markAllAsRead action on the notification bell clears all unread', function () {
    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create();

    $user->notify(new CutJobNotification($job, 'completed'));
    $user->notify(new CutJobNotification($job, 'failed'));

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->assertSet('unreadCount', 2)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
    expect($user->fresh()->notifications()->count())->toBe(2);
});

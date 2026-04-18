<?php

use App\Models\CutJob;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('monthly usage excludes failed jobs', function () {
    $user = User::factory()->create();

    // Create 2 completed and 3 failed jobs this month
    CutJob::factory()->for($user)->completed()->count(2)->create();
    CutJob::factory()->for($user)->failed()->count(3)->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    $usage = $component->instance()->monthlyUsage();

    expect($usage['used'])->toBe(2);
});

test('monthly usage respects usage_reset_at', function () {
    $user = User::factory()->create();

    // Create 3 completed jobs before the reset
    CutJob::factory()->for($user)->completed()->count(3)->create([
        'created_at' => now()->subHours(2),
    ]);

    // Reset usage 1 hour ago
    $user->update(['usage_reset_at' => now()->subHour()]);

    // Create 1 completed job after the reset
    CutJob::factory()->for($user)->completed()->create([
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    $usage = $component->instance()->monthlyUsage();

    expect($usage['used'])->toBe(1)
        ->and($usage['limit'])->toBe(config('cutjob.monthly_job_limit', 10));
});

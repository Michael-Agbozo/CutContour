<?php

use App\Models\CutJob;
use App\Models\User;
use App\Permission;

/*
|--------------------------------------------------------------------------
| Permission enum — gates resolve correctly
|--------------------------------------------------------------------------
*/

test('admin has access-admin permission', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can(Permission::AccessAdmin->value))->toBeTrue()
        ->and($admin->can(Permission::ManageUsers->value))->toBeTrue()
        ->and($admin->can(Permission::ManageSystem->value))->toBeTrue()
        ->and($admin->can(Permission::ViewAllJobs->value))->toBeTrue()
        ->and($admin->can(Permission::AccessWorkspace->value))->toBeFalse();
});

test('regular user has access-workspace permission', function () {
    $user = User::factory()->create(['is_admin' => false]);

    expect($user->can(Permission::AccessWorkspace->value))->toBeTrue()
        ->and($user->can(Permission::AccessAdmin->value))->toBeFalse()
        ->and($user->can(Permission::ManageUsers->value))->toBeFalse()
        ->and($user->can(Permission::ManageSystem->value))->toBeFalse()
        ->and($user->can(Permission::ViewAllJobs->value))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Middleware — non-admins get 403, guests redirect to login
|--------------------------------------------------------------------------
*/

test('guests are redirected from admin pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'admin.dashboard',
    'admin.jobs',
    'admin.failed-jobs',
    'admin.users',
    'admin.system',
    'admin.logs',
]);

test('non-admin users get 403 on admin pages', function (string $route) {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route($route))->assertForbidden();
})->with([
    'admin.dashboard',
    'admin.jobs',
    'admin.failed-jobs',
    'admin.users',
    'admin.system',
    'admin.logs',
]);

test('admin users can access admin pages', function (string $route) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route($route))->assertOk();
})->with([
    'admin.dashboard',
    'admin.jobs',
    'admin.failed-jobs',
    'admin.users',
    'admin.system',
    'admin.logs',
]);

/*
|--------------------------------------------------------------------------
| Login redirect — role-based
|--------------------------------------------------------------------------
*/

test('admin is redirected to admin dashboard after login', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'password',
    ]);

    $this->post(route('login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

test('regular user is redirected to dashboard after login', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'password' => 'password',
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

/*
|--------------------------------------------------------------------------
| Dashboard — stats render
|--------------------------------------------------------------------------
*/

test('admin dashboard shows job statistics', function () {
    $admin = User::factory()->admin()->create();
    CutJob::factory()->for($admin)->completed()->count(3)->create();
    CutJob::factory()->for($admin)->failed()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeText('4'); // total jobs
});

/*
|--------------------------------------------------------------------------
| Policy — admin bypass
|--------------------------------------------------------------------------
*/

test('admin can view any users job via policy bypass', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($other)->create();

    expect($admin->can('view', $job))->toBeTrue();
});

test('admin can download any users completed job', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();
    $job = CutJob::factory()->for($other)->completed()->create();

    expect($admin->can('download', $job))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Users — toggle admin
|--------------------------------------------------------------------------
*/

test('admin can toggle another users admin status', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['is_admin' => false]);

    Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleAdmin', $user->id);

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('admin cannot toggle their own admin status', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('toggleAdmin', $admin->id);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('non-admin cannot call toggleAdmin action', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $target = User::factory()->create(['is_admin' => false]);

    Livewire\Livewire::actingAs($user)
        ->test('pages::admin.users')
        ->call('toggleAdmin', $target->id)
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Jobs — delete with authorization
|--------------------------------------------------------------------------
*/

test('admin can delete a job via deleteJob action', function () {
    $admin = User::factory()->admin()->create();
    $job = CutJob::factory()->for($admin)->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.jobs')
        ->call('deleteJob', $job->id);

    expect(CutJob::find($job->id))->toBeNull();
});

test('non-admin cannot call deleteJob action', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $job = CutJob::factory()->for($user)->create();

    Livewire\Livewire::actingAs($user)
        ->test('pages::admin.jobs')
        ->call('deleteJob', $job->id)
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Logs — filtering and display
|--------------------------------------------------------------------------
*/

test('logs page shows log entries excluding ProcessCutJob', function () {
    $admin = User::factory()->admin()->create();

    $logPath = storage_path('logs/laravel.log');
    $original = file_exists($logPath) ? file_get_contents($logPath) : '';

    // Write test log entries
    file_put_contents($logPath, implode("\n", [
        '[2026-04-18 10:00:00] production.ERROR: ProcessCutJob: failed {"job_id":"abc"}',
        '[2026-04-18 10:01:00] production.ERROR: Something else went wrong {"context":"test"}',
        '[2026-04-18 10:02:00] production.WARNING: Disk space low {"free":"1GB"}',
    ])."\n");

    $response = Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.logs');

    $entries = $response->get('entries');

    // ProcessCutJob entry should be filtered out
    expect($entries)->toHaveCount(2)
        ->and($entries[0]['message'])->toContain('Disk space low')
        ->and($entries[1]['message'])->toContain('Something else went wrong');

    // Restore original log
    file_put_contents($logPath, $original);
});

test('logs page filters by level', function () {
    $admin = User::factory()->admin()->create();

    $logPath = storage_path('logs/laravel.log');
    $original = file_exists($logPath) ? file_get_contents($logPath) : '';

    file_put_contents($logPath, implode("\n", [
        '[2026-04-18 10:00:00] production.ERROR: Test error message',
        '[2026-04-18 10:01:00] production.WARNING: Test warning message',
        '[2026-04-18 10:02:00] production.INFO: Test info message',
    ])."\n");

    $response = Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.logs')
        ->set('level', 'error');

    $entries = $response->get('entries');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['level'])->toBe('error');

    file_put_contents($logPath, $original);
});

/*
|--------------------------------------------------------------------------
| Users — reset usage
|--------------------------------------------------------------------------
*/

test('admin can reset a free-tier users monthly usage', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['is_admin' => false]);

    expect($user->usage_reset_at)->toBeNull();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('resetUsage', $user->id);

    expect($user->fresh()->usage_reset_at)->not->toBeNull();
});

test('admin cannot reset usage for another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::admin.users')
        ->call('resetUsage', $otherAdmin->id);

    expect($otherAdmin->fresh()->usage_reset_at)->toBeNull();
});

test('non-admin cannot call resetUsage action', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $target = User::factory()->create(['is_admin' => false]);

    Livewire\Livewire::actingAs($user)
        ->test('pages::admin.users')
        ->call('resetUsage', $target->id)
        ->assertForbidden();
});

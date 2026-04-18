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

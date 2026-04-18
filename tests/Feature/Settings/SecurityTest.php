<?php

use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Password')
        ->assertSee('Email')
        ->assertSee('Passkeys')
        ->assertSee('Two-factor auth')
        ->assertSee('Enable 2FA');
});

test('unverified users can access security settings page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Send code');
});

test('security settings page is accessible without password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Password');
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Password')
        ->assertDontSee('Two-factor auth');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.security');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});

test('email verification code can be requested from security settings', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->call('sendEmailVerificationCode')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function ($notification) use ($user) {
        return Cache::get('email-verification-code:'.$user->id) === hash('sha256', $notification->code)
            && strlen($notification->code) === 6;
    });
});

test('email can be verified with a valid emailed code from security settings', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->call('sendEmailVerificationCode');

    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function ($notification) use ($user) {
        Livewire::test('pages::settings.security')
            ->set('email_verification_code', $notification->code)
            ->call('verifyEmailCode')
            ->assertHasNoErrors();

        return $user->fresh()->hasVerifiedEmail()
            && Cache::get('email-verification-code:'.$user->id) === null;
    });
});

test('invalid email verification code is rejected', function () {
    $user = User::factory()->unverified()->create();

    Cache::put('email-verification-code:'.$user->id, hash('sha256', '123456'), now()->addMinutes(10));

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->set('email_verification_code', '654321')
        ->call('verifyEmailCode')
        ->assertHasErrors(['email_verification_code']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

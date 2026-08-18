<?php

use App\Models\User;
use App\Notifications\EmailVerificationCodeNotification;

test('email verification notification uses mail channel only', function () {
    $notification = new EmailVerificationCodeNotification('123456');

    expect($notification->via(new stdClass))->toBe(['mail']);
});

test('mail message contains the verification code and the ten-minute expiry hint', function () {
    $user = User::factory()->make(['name' => 'Ada Lovelace']);
    $notification = new EmailVerificationCodeNotification('987654');

    $mail = $notification->toMail($user);

    $rendered = implode("\n", $mail->introLines);

    expect($mail->subject)->toContain('email verification code')
        ->and($mail->greeting)->toContain('Ada Lovelace')
        ->and($rendered)->toContain('987654')
        ->and($rendered)->toContain('10 minutes');
});

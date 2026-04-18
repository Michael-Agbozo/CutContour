<?php

use App\Notifications\EmailVerificationCodeNotification;
use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $email_verification_code = '';

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Send a fresh email verification code to the user.
     */
    public function sendEmailVerificationCode(): void
    {
        $user = Auth::user();

        if (! $this->hasUnverifiedEmail) {
            Flux::toast(variant: 'success', text: __('Your email address is already verified.'));

            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->emailVerificationCacheKey($user->getAuthIdentifier()), hash('sha256', $code), now()->addMinutes(10));

        $user->notify(new EmailVerificationCodeNotification($code));

        Flux::toast(variant: 'success', text: __('A verification code has been sent to your email address.'));
    }

    /**
     * Verify the user's email with the emailed code.
     */
    public function verifyEmailCode(): void
    {
        $user = Auth::user();

        if (! $this->hasUnverifiedEmail) {
            Flux::toast(variant: 'success', text: __('Your email address is already verified.'));

            return;
        }

        $this->validate([
            'email_verification_code' => ['required', 'digits:6'],
        ]);

        $expectedCodeHash = Cache::get($this->emailVerificationCacheKey($user->getAuthIdentifier()));

        if (! is_string($expectedCodeHash) || ! hash_equals($expectedCodeHash, hash('sha256', $this->email_verification_code))) {
            throw ValidationException::withMessages([
                'email_verification_code' => __('The verification code is invalid or has expired.'),
            ]);
        }

        $user->markEmailAsVerified();

        Cache::forget($this->emailVerificationCacheKey($user->getAuthIdentifier()));

        $this->reset('email_verification_code');

        Flux::toast(variant: 'success', text: __('Your email address has been verified.'));
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    private function emailVerificationCacheKey(int|string $userId): string
    {
        return 'email-verification-code:'.$userId;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Security')"
        :subheading="__('Manage your account security — password, email, two-factor authentication, and passkeys.')"
        :wide="true"
    >
        <div class="mt-6 space-y-4">

            {{-- ── Password ─────────────────────────────────────────────────── --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="flex items-center gap-3 border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <svg class="size-4 text-zinc-600 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Password') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Change your account password') }}</p>
                    </div>
                </div>

                <div class="p-6">
                    <form wire:submit="updatePassword" class="grid gap-4 sm:grid-cols-3">
                        <flux:input
                            wire:model="current_password"
                            :label="__('Current password')"
                            type="password"
                            required
                            autocomplete="current-password"
                            viewable
                        />
                        <flux:input
                            wire:model="password"
                            :label="__('New password')"
                            type="password"
                            required
                            autocomplete="new-password"
                            viewable
                        />
                        <flux:input
                            wire:model="password_confirmation"
                            :label="__('Confirm password')"
                            type="password"
                            required
                            autocomplete="new-password"
                            viewable
                        />
                        <div class="sm:col-span-3">
                            <flux:button variant="primary" type="submit" data-test="update-password-button">
                                {{ __('Update password') }}
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Feature cards row ─────────────────────────────────────────── --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Email verification card --}}
                <div class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/60"
                     x-data="{ verifying: false }">
                    <div class="flex items-start justify-between p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl
                                        {{ $this->hasUnverifiedEmail ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-emerald-50 dark:bg-emerald-900/20' }}">
                                <svg class="size-5 {{ $this->hasUnverifiedEmail ? 'text-amber-500' : 'text-emerald-500' }}"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Email') }}</p>
                                <span class="inline-flex items-center gap-1 text-xs font-medium
                                             {{ $this->hasUnverifiedEmail ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    <span class="size-1.5 rounded-full {{ $this->hasUnverifiedEmail ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                    {{ $this->hasUnverifiedEmail ? __('Unverified') : __('Verified') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col justify-between gap-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        @if ($this->hasUnverifiedEmail)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Verify :email to unlock full access. Code expires in 10 minutes.', ['email' => Auth::user()->email]) }}
                            </p>

                            <div x-show="!verifying" class="flex flex-col gap-2">
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    wire:click="sendEmailVerificationCode"
                                    @click="verifying = true"
                                    data-test="send-email-verification-code-button"
                                >
                                    {{ __('Send code') }}
                                </flux:button>
                            </div>

                            <div x-show="verifying" x-cloak class="space-y-3">
                                <flux:otp wire:model="email_verification_code" length="6" label="{{ __('Enter code') }}" />
                                <div class="flex gap-2">
                                    <flux:button variant="primary" size="sm" wire:click="verifyEmailCode" data-test="verify-email-code-button">
                                        {{ __('Verify') }}
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" @click="verifying = false">
                                        {{ __('Back') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Your email address :email is verified.', ['email' => Auth::user()->email]) }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- 2FA card --}}
                @if ($canManageTwoFactor)
                <div class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/60" wire:cloak>
                    <div class="flex items-start justify-between p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl
                                        {{ $twoFactorEnabled ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                                <svg class="size-5 {{ $twoFactorEnabled ? 'text-emerald-500' : 'text-zinc-500 dark:text-zinc-400' }}"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Two-factor auth') }}</p>
                                <span class="inline-flex items-center gap-1 text-xs font-medium
                                             {{ $twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    <span class="size-1.5 rounded-full {{ $twoFactorEnabled ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                    {{ $twoFactorEnabled ? __('Enabled') : __('Not enabled') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col justify-between gap-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        @if ($twoFactorEnabled)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Your account is protected with an authenticator app.') }}
                            </p>
                            <div class="space-y-2">
                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                                <flux:button variant="danger" size="sm" wire:click="disable">
                                    {{ __('Disable 2FA') }}
                                </flux:button>
                            </div>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Add an authenticator app (Google Authenticator, Authy) for extra login security.') }}
                            </p>
                            <div>
                                <flux:modal.trigger name="two-factor-setup-modal">
                                    <flux:button variant="primary" size="sm" wire:click="$dispatch('start-two-factor-setup')">
                                        {{ __('Enable 2FA') }}
                                    </flux:button>
                                </flux:modal.trigger>
                                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Passkeys card --}}
                <div class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/60">
                    <div class="flex items-start justify-between p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                <svg class="size-5 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Passkeys') }}</p>
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                    <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                    {{ __('Coming soon') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col justify-between gap-4 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Sign in with Face ID, Touch ID, or a hardware security key — no password required.') }}
                        </p>
                        <div>
                            <flux:button variant="ghost" size="sm" disabled class="opacity-50">
                                {{ __('Add a passkey') }}
                            </flux:button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </x-pages::settings.layout>
</section>

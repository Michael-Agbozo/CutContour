<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';
    public string $email = '';
    public $avatar = null;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updatedAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $this->avatar->storeAs(
            'avatars',
            $user->id.'.'.$this->avatar->getClientOriginalExtension(),
            'public',
        );

        $user->update(['avatar_path' => $path]);

        $this->reset('avatar');

        Flux::toast(variant: 'success', text: __('Profile picture updated.'));
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        Flux::toast(variant: 'success', text: __('Profile picture removed.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name, email address, and profile picture')">

        {{-- Avatar --}}
        <div class="my-6 space-y-4"
             x-data="{
                preview: @js(Auth::user()->avatarUrl()),
                loading: false,
                pickFile(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.loading = true;
                    const reader = new FileReader();
                    reader.onload = e => { this.preview = e.target.result; };
                    reader.readAsDataURL(file);
                }
             }"
             x-on:livewire-upload-finish="loading = false"
             x-on:livewire-upload-error="loading = false">

            <flux:label>{{ __('Profile picture') }}</flux:label>

            <div class="flex items-center gap-5">
                {{-- Avatar preview --}}
                <div class="relative shrink-0">
                    <template x-if="preview">
                        <img :src="preview" alt="Avatar" class="size-20 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700" />
                    </template>
                    <template x-if="!preview">
                        <div class="flex size-20 items-center justify-center rounded-full bg-cutcontour/10 text-xl font-semibold text-cutcontour ring-2 ring-zinc-200 dark:ring-zinc-700">
                            {{ Auth::user()->initials() }}
                        </div>
                    </template>

                    {{-- Uploading spinner overlay --}}
                    <template x-if="loading">
                        <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40">
                            <svg class="size-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </template>
                </div>

                <div class="flex flex-col gap-2">
                    <input
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        x-ref="fileInput"
                        wire:model="avatar"
                        @change="pickFile($event)"
                    />

                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="$refs.fileInput.click()"
                    >
                        {{ __('Choose photo') }}
                    </flux:button>

                    @if (Auth::user()->avatar_path)
                        <flux:button size="sm" variant="ghost" wire:click="removeAvatar" class="text-red-500 hover:text-red-600">
                            {{ __('Remove') }}
                        </flux:button>
                    @endif

                    <flux:text class="text-xs text-zinc-400">JPG, PNG or GIF · max 2 MB</flux:text>
                </div>
            </div>

            @error('avatar')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <flux:separator />

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>

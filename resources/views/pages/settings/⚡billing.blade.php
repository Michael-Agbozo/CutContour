<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Billing settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Billing')" :subheading="__('Manage your plan and billing details')">
        {{-- Current plan --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Current Plan') }}</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Your active subscription') }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                    {{ __('MVP Access') }}
                </span>
            </div>

            <flux:separator class="my-4" />

            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Plan') }}</dt>
                    <dd class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Free') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('File size limit') }}</dt>
                    <dd class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('100 MB') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('File retention') }}</dt>
                    <dd class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('90 days') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Processing') }}</dt>
                    <dd class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('AI-Enhanced + Fast Path') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Coming soon note --}}
        <div class="mt-6 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/30">
            <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Paid plans coming soon') }}</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-600">{{ __('We\'re working on additional plans with higher limits, batch processing, and API access. Stay tuned.') }}</p>
        </div>
    </x-pages::settings.layout>
</section>

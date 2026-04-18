<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark"
      x-data
      x-init="
        const saved = localStorage.getItem('cc-theme');
        if (saved === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
      ">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:sidebar sticky collapsible
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">

            <flux:sidebar.header>
                <flux:sidebar.brand wire:navigate :href="route('dashboard')">
                    <x-slot name="logo">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-cutcontour text-white shadow-sm">
                            <svg viewBox="0 0 26 26" fill="none" class="size-4.5">
                                <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                                      stroke="currentColor" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                                <rect x="7" y="7" width="12" height="12" rx="2.5" fill="currentColor"/>
                            </svg>
                        </div>
                    </x-slot>
                    CutContour
                </flux:sidebar.brand>
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                {{-- Workspace (regular users only) --}}
                @can('access-workspace')
                <flux:sidebar.group :heading="__('Workspace')" icon="squares-2x2" expandable>
                    <flux:sidebar.item
                        icon="squares-2x2"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="plus-circle"
                        :href="route('jobs.create')"
                        :current="request()->routeIs('jobs.create')"
                        wire:navigate
                    >
                        {{ __('New Job') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Recent Jobs --}}
                <flux:sidebar.group :heading="__('Recent Jobs')" icon="clock" expandable>
                    @php
                        $recentJobs = [];
                        // Replace with: auth()->user()->cutJobs()->latest()->take(5)->get()
                    @endphp

                    @forelse($recentJobs as $job)
                        <flux:sidebar.item
                            :href="route('jobs.create')"
                            wire:navigate
                        >
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
                                <span class="truncate text-sm">{{ $job->original_name }}</span>
                                @php
                                    $dot = match($job->status) {
                                        'completed'  => 'bg-emerald-500',
                                        'processing' => 'bg-amber-500',
                                        'failed'     => 'bg-red-500',
                                        default      => 'bg-zinc-400',
                                    };
                                @endphp
                                <span class="size-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                            </div>
                        </flux:sidebar.item>
                    @empty
                        <div class="px-3 py-5 text-center">
                            <p class="text-xs text-zinc-500 dark:text-zinc-500">No jobs yet.</p>
                            <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-600">Upload artwork to get started.</p>
                        </div>
                    @endforelse
                </flux:sidebar.group>
                @endcan

                {{-- Admin (super admins only) --}}
                @can('access-admin')
                    <flux:sidebar.group :heading="__('Admin')" icon="shield-check" expandable>
                        <flux:sidebar.item
                            icon="chart-bar-square"
                            :href="route('admin.dashboard')"
                            :current="request()->routeIs('admin.dashboard')"
                            wire:navigate
                        >
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="queue-list"
                            :href="route('admin.jobs')"
                            :current="request()->routeIs('admin.jobs')"
                            wire:navigate
                        >
                            {{ __('All Jobs') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="exclamation-triangle"
                            :href="route('admin.failed-jobs')"
                            :current="request()->routeIs('admin.failed-jobs')"
                            wire:navigate
                        >
                            {{ __('Failed Jobs') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="users"
                            :href="route('admin.users')"
                            :current="request()->routeIs('admin.users')"
                            wire:navigate
                        >
                            {{ __('Users') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="cog-6-tooth"
                            :href="route('admin.system')"
                            :current="request()->routeIs('admin.system')"
                            wire:navigate
                        >
                            {{ __('System') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="document-magnifying-glass"
                            :href="route('admin.logs')"
                            :current="request()->routeIs('admin.logs')"
                            wire:navigate
                        >
                            {{ __('Error Logs') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Notification bell (desktop) --}}
            <div class="hidden lg:flex items-center gap-3 px-3 py-1 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:px-0">
                <livewire:notification-bell />
                <a href="{{ route('notifications.index') }}"
                   wire:navigate
                   class="in-data-flux-sidebar-collapsed-desktop:hidden text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                    Notifications
                </a>
            </div>

            {{-- Theme toggle (desktop) --}}
            <div class="hidden lg:flex items-center gap-3 px-3 py-2 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:px-0"
                 x-data="{
                    dark: document.documentElement.classList.contains('dark'),
                    toggle() {
                        this.dark = !this.dark;
                        document.documentElement.classList.toggle('dark', this.dark);
                        localStorage.setItem('cc-theme', this.dark ? 'dark' : 'light');
                    }
                 }">
                <button @click="toggle()"
                    class="relative flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300 transition-colors">
                    <svg x-show="dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg x-show="!dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>
                <span class="in-data-flux-sidebar-collapsed-desktop:hidden text-xs text-zinc-400 dark:text-zinc-600" x-text="dark ? 'Dark mode' : 'Light mode'"></span>
            </div>

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        {{-- Mobile header --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <div class="flex items-center gap-2">
                <div class="flex size-6 items-center justify-center rounded bg-cutcontour text-white">
                    <svg viewBox="0 0 26 26" fill="none" class="size-3.5">
                        <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                              stroke="currentColor" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                        <rect x="7" y="7" width="12" height="12" rx="2.5" fill="currentColor"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold dark:text-white">CutContour</span>
            </div>
            <flux:spacer />

            {{-- Notification bell (mobile) --}}
            <livewire:notification-bell />

            {{-- Theme toggle (mobile) --}}
            <button
                x-data="{
                    dark: document.documentElement.classList.contains('dark'),
                    toggle() {
                        this.dark = !this.dark;
                        document.documentElement.classList.toggle('dark', this.dark);
                        localStorage.setItem('cc-theme', this.dark ? 'dark' : 'light');
                    }
                }"
                @click="toggle()"
                class="flex size-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                <svg x-show="dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                <svg x-show="!dark" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
            </button>

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    :avatar="auth()->user()->avatarUrl()"
                    icon-trailing="chevron-down"
                />
                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="flex items-center gap-2 px-1 py-1.5">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" :src="auth()->user()->avatarUrl()" />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>{{ __('Profile') }}</flux:menu.item>
                    <flux:menu.item :href="route('billing.edit')" icon="credit-card" wire:navigate>{{ __('Billing') }}</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full" onsubmit="event.preventDefault(); var f=this; document.dispatchEvent(new CustomEvent('toast-show', { detail: { duration: 2500, slots: { text: 'Successfully logged out. See you next time!' }, dataset: { variant: 'success' } } })); setTimeout(() => f.submit(), 2000);">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @if(session('flux_toast'))
            @php $fluxToast = session('flux_toast'); @endphp
            <script>
                document.addEventListener('alpine:initialized', function () {
                    document.dispatchEvent(new CustomEvent('toast-show', {
                        detail: {
                            duration: 4000,
                            slots:   { text: @js($fluxToast['text']) },
                            dataset: { variant: @js($fluxToast['variant'] ?? 'success') }
                        }
                    }));
                });
            </script>
        @endif

        @fluxScripts
    </body>
</html>

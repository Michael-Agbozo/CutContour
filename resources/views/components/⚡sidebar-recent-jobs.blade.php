<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /**
     * The five most recent jobs for the signed-in user.
     *
     * Cached per Livewire component instance so `wire:navigate` transitions and
     * unrelated re-renders across the app don't re-hit the DB. Actions that mutate
     * the user's job set (bell notifications firing on job status changes) can
     * bust this cache by dispatching `notification-count-changed`.
     */
    #[Computed(cache: true, key: 'sidebar-recent-jobs')]
    public function recentJobs(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->cutJobs()
            ->select(['id', 'original_name', 'job_name', 'status'])
            ->latest()
            ->take(5)
            ->get();
    }

    #[On('notification-count-changed')]
    public function refresh(): void
    {
        unset($this->recentJobs);
    }
};

?>

<div>
    <flux:sidebar.group :heading="__('Recent Jobs')" icon="clock" expandable>
        @forelse($this->recentJobs as $job)
            {{-- There is no per-job detail page; link to the dashboard where the
                 matching row exposes the download action for completed jobs. --}}
            <flux:sidebar.item
                :href="route('dashboard')"
                wire:navigate
            >
                <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
                    <span class="truncate text-sm">{{ $job->job_name ?: $job->original_name }}</span>
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
</div>

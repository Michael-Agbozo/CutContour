<?php

use App\Jobs\ProcessCutJob;
use App\Models\CutJob;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Failed Jobs — Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $expandedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function jobs(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return CutJob::query()
            ->with('user')
            ->where('status', 'failed')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('original_name', 'like', "%{$this->search}%")
                    ->orWhere('error_message', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->search}%"));
            }))
            ->latest()
            ->paginate(20);
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function retryJob(string $id): void
    {
        $job = CutJob::find($id);

        if (! $job || $job->status !== 'failed') {
            return;
        }

        $job->update([
            'status' => 'processing',
            'error_message' => null,
            'processing_duration_ms' => null,
        ]);

        ProcessCutJob::dispatch($job);

        Flux::toast('Job re-queued for processing.');
    }

    public function deleteJob(string $id): void
    {
        CutJob::find($id)?->delete();
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Failed Jobs</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Inspect errors and retry failed processing jobs.</p>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('admin.dashboard')" wire:navigate icon="arrow-left">
            Back to Dashboard
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="w-full sm:w-64">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search files, users, errors…" icon="magnifying-glass" />
    </div>

    {{-- Failed jobs list --}}
    <div class="space-y-3">
        @forelse($this->jobs as $job)
            <div wire:key="failed-{{ $job->id }}"
                 class="overflow-hidden rounded-xl border border-red-200/60 bg-white dark:border-red-900/30 dark:bg-zinc-900">

                {{-- Row --}}
                <div class="flex cursor-pointer items-center gap-4 px-5 py-4 transition-colors hover:bg-red-50/30 dark:hover:bg-red-950/10"
                     wire:click="toggleExpand('{{ $job->id }}')">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                        <flux:icon name="x-circle" class="size-4 text-red-500" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $job->original_name }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $job->user?->name ?? 'Deleted user' }}
                            · {{ $job->created_at->diffForHumans() }}
                            @if($job->processing_duration_ms)
                                · {{ number_format($job->processing_duration_ms / 1000, 1) }}s
                            @endif
                        </p>
                    </div>
                    <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform {{ $expandedId === $job->id ? 'rotate-180' : '' }}" />
                </div>

                {{-- Expanded detail --}}
                @if($expandedId === $job->id)
                    <div class="border-t border-red-100 bg-red-50/30 px-5 py-4 dark:border-red-900/20 dark:bg-red-950/10">
                        <div class="space-y-3">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Error Message</p>
                                <div class="mt-1 rounded-lg border border-red-200 bg-white px-3 py-2.5 dark:border-red-900/40 dark:bg-zinc-900">
                                    <p class="whitespace-pre-wrap break-all text-xs leading-relaxed text-red-700 dark:text-red-400">{{ $job->error_message ?? 'No error message recorded.' }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">File Type</p>
                                    <p class="mt-0.5 text-xs text-zinc-700 dark:text-zinc-300">{{ strtoupper($job->file_type) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Dimensions</p>
                                    <p class="mt-0.5 text-xs text-zinc-700 dark:text-zinc-300">{{ $job->width }} × {{ $job->height }}px</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">AI Used</p>
                                    <p class="mt-0.5 text-xs text-zinc-700 dark:text-zinc-300">{{ $job->ai_used ? 'Yes' : 'No' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Confidence</p>
                                    <p class="mt-0.5 text-xs text-zinc-700 dark:text-zinc-300">{{ $job->confidence_score !== null ? number_format($job->confidence_score, 2) : '—' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <flux:button wire:click="retryJob('{{ $job->id }}')" variant="primary" size="sm" icon="arrow-path">
                                    Retry
                                </flux:button>
                                <div x-data="{ confirm: false }">
                                    <flux:button x-show="!confirm" @click="confirm = true" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600">
                                        Delete
                                    </flux:button>
                                    <div x-show="confirm" x-cloak class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2 py-1 shadow-sm dark:border-red-900/40 dark:bg-zinc-900">
                                        <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">Sure?</span>
                                        <button
                                            @click="$wire.deleteJob('{{ $job->id }}'); confirm = false"
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-red-600 hover:bg-red-50 dark:text-red-400"
                                        >Yes</button>
                                        <button
                                            @click="confirm = false"
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        >No</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex items-center gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-5 py-8 dark:border-zinc-800 dark:bg-zinc-900/50">
                <div class="flex size-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon name="check-circle" class="size-4 text-emerald-500" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No failed jobs{{ $search ? ' matching your search' : '' }}.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div>
        {{ $this->jobs->links() }}
    </div>

</div>

<?php

use App\Models\CutJob;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All Jobs — Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $aiFilter = '';

    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedAiFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    #[Computed]
    public function jobs(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return CutJob::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('original_name', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%"));
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->aiFilter !== '', fn ($q) => $q->where('ai_used', $this->aiFilter === 'ai'))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);
    }

    public function deleteJob(string $id): void
    {
        $job = CutJob::find($id);

        if ($job) {
            $job->delete();
        }
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">All Jobs</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Browse and inspect all user jobs.</p>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('admin.dashboard')" wire:navigate icon="arrow-left">
            Back to Dashboard
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-64">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search files or users…" icon="magnifying-glass" />
        </div>
        <flux:select wire:model.live="status" class="w-40">
            <option value="">All statuses</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="expired">Expired</option>
        </flux:select>
        <flux:select wire:model.live="aiFilter" class="w-36">
            <option value="">All paths</option>
            <option value="ai">AI Path</option>
            <option value="fast">Fast Path</option>
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">File</th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">User</th>
                    <th wire:click="sort('status')" class="cursor-pointer px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        Status
                        @if($sortBy === 'status')
                            <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Path</th>
                    <th wire:click="sort('confidence_score')" class="cursor-pointer px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        Confidence
                        @if($sortBy === 'confidence_score')
                            <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th wire:click="sort('processing_duration_ms')" class="cursor-pointer px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        Duration
                        @if($sortBy === 'processing_duration_ms')
                            <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th wire:click="sort('created_at')" class="cursor-pointer px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        Created
                        @if($sortBy === 'created_at')
                            <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                @forelse($this->jobs as $job)
                    <tr class="group hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="job-{{ $job->id }}">
                        <td class="max-w-[180px] truncate px-4 py-3 text-xs font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $job->original_name }}
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $job->user?->name ?? 'Deleted' }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match($job->status) {
                                    'completed'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'processing' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'failed'     => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'expired'    => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500',
                                    default      => 'bg-zinc-100 text-zinc-500',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badge }}">
                                {{ ucfirst($job->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $job->ai_used ? 'AI' : 'Fast' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $job->confidence_score !== null ? number_format($job->confidence_score, 2) : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $job->processing_duration_ms ? number_format($job->processing_duration_ms / 1000, 1) . 's' : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-400">
                            {{ $job->created_at->diffForHumans() }}
                        </td>
                        <td class="px-4 py-3">
                            <div x-data="{ confirm: false }" class="flex items-center gap-1">
                                <button
                                    x-show="!confirm"
                                    @click="confirm = true"
                                    class="rounded p-1 text-zinc-300 opacity-0 transition-all hover:bg-red-50 hover:text-red-500 group-hover:opacity-100 dark:text-zinc-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                                    title="Delete"
                                >
                                    <flux:icon name="trash" class="size-3.5" />
                                </button>
                                <div x-show="confirm" x-cloak class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2 py-1 shadow-sm dark:border-red-900/40 dark:bg-zinc-900">
                                    <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">Sure?</span>
                                    <button
                                        @click="$wire.deleteJob('{{ $job->id }}'); confirm = false"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                                    >Yes</button>
                                    <button
                                        @click="confirm = false"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    >No</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                            No jobs found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>
        {{ $this->jobs->links() }}
    </div>

</div>

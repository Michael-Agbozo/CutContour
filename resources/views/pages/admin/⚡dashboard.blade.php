<?php

use App\Models\CutJob;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin Dashboard')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            'total_users' => User::count(),
            'total_jobs' => CutJob::count(),
            'jobs_today' => CutJob::whereDate('created_at', today())->count(),
            'jobs_this_month' => CutJob::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'completed' => CutJob::where('status', 'completed')->count(),
            'failed' => CutJob::where('status', 'failed')->count(),
            'processing' => CutJob::where('status', 'processing')->count(),
            'expired' => CutJob::where('status', 'expired')->count(),
            'ai_used' => CutJob::where('ai_used', true)->count(),
            'fast_path' => CutJob::where('ai_used', false)->whereIn('status', ['completed', 'failed'])->count(),
            'avg_duration_ms' => (int) CutJob::where('status', 'completed')->avg('processing_duration_ms'),
            'avg_confidence' => round((float) CutJob::whereNotNull('confidence_score')->avg('confidence_score'), 2),
        ];
    }

    #[Computed]
    public function recentFailures(): \Illuminate\Database\Eloquent\Collection
    {
        return CutJob::with('user')
            ->where('status', 'failed')
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function failureRate(): float
    {
        $total = CutJob::whereIn('status', ['completed', 'failed'])->count();

        if ($total === 0) {
            return 0;
        }

        return round(CutJob::where('status', 'failed')->count() / $total * 100, 1);
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Admin Dashboard</h1>
        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">System overview and health metrics.</p>
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @php
            $cards = [
                ['label' => 'Total Users', 'value' => $this->stats['total_users'], 'icon' => 'users', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50 dark:bg-blue-950/30'],
                ['label' => 'Total Jobs', 'value' => $this->stats['total_jobs'], 'icon' => 'document-text', 'color' => 'text-zinc-500', 'bg' => 'bg-zinc-100 dark:bg-zinc-800'],
                ['label' => 'Jobs Today', 'value' => $this->stats['jobs_today'], 'icon' => 'calendar', 'color' => 'text-cutcontour', 'bg' => 'bg-pink-50 dark:bg-pink-950/30'],
                ['label' => 'This Month', 'value' => $this->stats['jobs_this_month'], 'icon' => 'chart-bar', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50 dark:bg-indigo-950/30'],
                ['label' => 'Completed', 'value' => $this->stats['completed'], 'icon' => 'check-circle', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/30'],
                ['label' => 'Failed', 'value' => $this->stats['failed'], 'icon' => 'x-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-50 dark:bg-red-950/30'],
                ['label' => 'Processing', 'value' => $this->stats['processing'], 'icon' => 'arrow-path', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50 dark:bg-amber-950/30'],
                ['label' => 'Expired', 'value' => $this->stats['expired'], 'icon' => 'clock', 'color' => 'text-zinc-400', 'bg' => 'bg-zinc-50 dark:bg-zinc-800'],
            ];
        @endphp

        @foreach($cards as $card)
            <div class="rounded-xl border border-zinc-200 bg-white px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg {{ $card['bg'] }}">
                        <flux:icon :name="$card['icon']" class="size-4.5 {{ $card['color'] }}" />
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($card['value']) }}</p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pipeline metrics --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Failure Rate</p>
            <p class="mt-1 text-2xl font-bold {{ $this->failureRate > 10 ? 'text-red-500' : 'text-emerald-500' }}">
                {{ $this->failureRate }}%
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Avg Processing Time</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->stats['avg_duration_ms'] > 0 ? number_format($this->stats['avg_duration_ms'] / 1000, 1) . 's' : '—' }}
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">AI Path Usage</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->stats['ai_used'] }}
                <span class="text-sm font-normal text-zinc-400">/ {{ $this->stats['ai_used'] + $this->stats['fast_path'] }}</span>
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Avg Confidence</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $this->stats['avg_confidence'] > 0 ? $this->stats['avg_confidence'] : '—' }}
            </p>
        </div>
    </div>

    {{-- Recent failures --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Recent Failures</h2>
            <flux:button variant="ghost" size="sm" :href="route('admin.failed-jobs')" wire:navigate>
                View all
            </flux:button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            {{-- Desktop table --}}
            <table class="hidden w-full text-left text-sm sm:table">
                <thead class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">File</th>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">User</th>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Error</th>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse($this->recentFailures as $job)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="max-w-[200px] truncate px-4 py-3 text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $job->original_name }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $job->user?->name ?? 'Deleted' }}
                            </td>
                            <td class="max-w-[250px] truncate px-4 py-3 text-xs text-red-600 dark:text-red-400">
                                {{ $job->error_message ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-400">
                                {{ $job->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                                No failures recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Mobile cards --}}
            <div class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900 sm:hidden">
                @forelse($this->recentFailures as $job)
                    <div class="flex flex-col gap-1.5 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $job->original_name }}</span>
                            <span class="shrink-0 text-[10px] text-zinc-400">{{ $job->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $job->user?->name ?? 'Deleted' }}</p>
                        <p class="line-clamp-2 text-[11px] text-red-600 dark:text-red-400">{{ $job->error_message ?? '—' }}</p>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                        No failures recorded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

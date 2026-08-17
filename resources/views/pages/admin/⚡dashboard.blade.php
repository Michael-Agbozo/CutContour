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
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        // Single aggregate query replaces 11 separate COUNTs / AVGs.
        // Sargable date comparisons keep the created_at index in play.
        $row = CutJob::query()
            ->selectRaw('COUNT(*) AS total_jobs')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS jobs_today', [$todayStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS jobs_this_month', [$monthStart])
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing")
            ->selectRaw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired")
            ->selectRaw('SUM(CASE WHEN ai_used = 1 THEN 1 ELSE 0 END) AS ai_used')
            ->selectRaw("SUM(CASE WHEN ai_used = 0 AND status IN ('completed', 'failed') THEN 1 ELSE 0 END) AS fast_path")
            ->selectRaw("AVG(CASE WHEN status = 'completed' THEN processing_duration_ms END) AS avg_duration_ms")
            ->selectRaw('AVG(confidence_score) AS avg_confidence')
            ->first();

        return [
            'total_users' => User::count(),
            'total_jobs' => (int) ($row->total_jobs ?? 0),
            'jobs_today' => (int) ($row->jobs_today ?? 0),
            'jobs_this_month' => (int) ($row->jobs_this_month ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'processing' => (int) ($row->processing ?? 0),
            'expired' => (int) ($row->expired ?? 0),
            'ai_used' => (int) ($row->ai_used ?? 0),
            'fast_path' => (int) ($row->fast_path ?? 0),
            'avg_duration_ms' => (int) ($row->avg_duration_ms ?? 0),
            'avg_confidence' => round((float) ($row->avg_confidence ?? 0), 2),
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
        $completed = $this->stats['completed'];
        $failed = $this->stats['failed'];
        $total = $completed + $failed;

        if ($total === 0) {
            return 0;
        }

        return round($failed / $total * 100, 1);
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <x-page-header title="Admin Dashboard" description="System overview and health metrics." />

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
            <x-stat-card :label="$card['label']" :value="number_format($card['value'])" :icon="$card['icon']" :icon-color="$card['color']" :icon-bg="$card['bg']" />
        @endforeach
    </div>

    {{-- Pipeline metrics --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            label="Failure Rate"
            :value="$this->failureRate . '%'"
            :value-class="$this->failureRate > 10 ? 'text-red-500' : 'text-emerald-500'"
        />
        <x-stat-card
            label="Avg Processing Time"
            :value="$this->stats['avg_duration_ms'] > 0 ? number_format($this->stats['avg_duration_ms'] / 1000, 1) . 's' : '—'"
        />
        <x-stat-card label="AI Path Usage">
            <x-slot:value>
                {{ $this->stats['ai_used'] }}
                <span class="text-sm font-normal text-zinc-400">/ {{ $this->stats['ai_used'] + $this->stats['fast_path'] }}</span>
            </x-slot:value>
        </x-stat-card>
        <x-stat-card
            label="Avg Confidence"
            :value="$this->stats['avg_confidence'] > 0 ? $this->stats['avg_confidence'] : '—'"
        />
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

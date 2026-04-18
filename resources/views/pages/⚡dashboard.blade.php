<?php

use App\Models\CutJob;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Dashboard')] class extends Component {
    use WithPagination;

    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();

        $totalJobs = $user->cutJobs()->count();
        $completed = $user->cutJobs()->where('status', 'completed')->count();
        $thisMonth = $user->cutJobs()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $storageMb = 0;
        $files = $user->cutJobs()->whereNotNull('file_path')->pluck('file_path');
        foreach ($files as $path) {
            if (Storage::exists($path)) {
                $storageMb += Storage::size($path);
            }
        }
        $storageMb = round($storageMb / 1024 / 1024, 1);

        return [
            'total_jobs' => $totalJobs,
            'completed' => $completed,
            'this_month' => $thisMonth,
            'storage_mb' => $storageMb,
        ];
    }

    #[Computed]
    public function jobs(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return auth()->user()
            ->cutJobs()
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function monthlyUsage(): array
    {
        $used = auth()->user()
            ->cutJobs()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $limit = 10; // Starter plan default

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $limit > 0 ? min(100, round($used / $limit * 100)) : 0,
        ];
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ explode(' ', auth()->user()->name)[0] }}.
            </h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                Here's your workspace overview.
            </p>
        </div>
        <flux:button
            variant="primary"
            icon="plus"
            :href="route('jobs.create')"
            wire:navigate
        >
            New Job
        </flux:button>
    </div>

    {{-- ── Stats grid ──────────────────────────────────────────── --}}
    @php
        $cards = [
            [
                'label'   => 'Total Jobs',
                'value'   => number_format($this->stats['total_jobs']),
                'sub'     => 'All time',
                'icon'    => 'document-text',
                'color'   => 'text-zinc-400 dark:text-zinc-500',
                'bg'      => 'bg-zinc-100 dark:bg-zinc-800',
            ],
            [
                'label'   => 'Completed',
                'value'   => number_format($this->stats['completed']),
                'sub'     => 'Ready to download',
                'icon'    => 'check-circle',
                'color'   => 'text-emerald-500',
                'bg'      => 'bg-emerald-50 dark:bg-emerald-950/30',
            ],
            [
                'label'   => 'This Month',
                'value'   => number_format($this->stats['this_month']),
                'sub'     => 'Jobs created',
                'icon'    => 'calendar',
                'color'   => 'text-cutcontour',
                'bg'      => 'bg-pink-50 dark:bg-pink-950/30',
            ],
            [
                'label'   => 'Storage Used',
                'value'   => $this->stats['storage_mb'] . ' MB',
                'sub'     => 'Of allocated storage',
                'icon'    => 'archive-box',
                'color'   => 'text-amber-500',
                'bg'      => 'bg-amber-50 dark:bg-amber-950/30',
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
        <div class="flex items-start gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $card['bg'] }}">
                <flux:icon :icon="$card['icon']" class="size-5 {{ $card['color'] }}" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    {{ $card['label'] }}
                </p>
                <p class="mt-0.5 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $card['value'] }}
                </p>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-600">
                    {{ $card['sub'] }}
                </p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Usage bar ────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Monthly usage</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $this->monthlyUsage['used'] }} of {{ $this->monthlyUsage['limit'] }} jobs used — Starter plan</p>
            </div>
            <flux:badge color="zinc" size="sm">Free</flux:badge>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-cutcontour transition-all duration-500" style="width: {{ $this->monthlyUsage['percent'] }}%"></div>
        </div>
        <div class="mt-2 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-600">
            <span>{{ $this->monthlyUsage['used'] }} jobs used</span>
            <a href="{{ route('billing.edit') }}" wire:navigate
               class="font-medium text-cutcontour hover:underline">
                Upgrade plan →
            </a>
        </div>
    </div>

    {{-- ── Recent jobs ──────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Recent jobs</h2>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">Your processing jobs</p>
            </div>
        </div>

        @if($this->jobs->isEmpty())
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div class="mb-4 flex size-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                <svg width="24" height="24" viewBox="0 0 26 26" fill="none" class="text-zinc-400 dark:text-zinc-500">
                    <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                          stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                    <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c" opacity="0.4"/>
                </svg>
            </div>
            <h3 class="mb-1 text-sm font-semibold text-zinc-900 dark:text-zinc-100">No jobs yet</h3>
            <p class="mb-5 max-w-xs text-sm text-zinc-400 dark:text-zinc-500">
                Upload your first design to generate a print-ready PDF with a CutContour spot colour path.
            </p>
            <flux:button variant="primary" icon="plus" :href="route('jobs.create')" wire:navigate>
                Create your first job
            </flux:button>
        </div>
        @else
        {{-- Jobs table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">File</th>
                        <th class="hidden px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 sm:table-cell">Dimensions</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="hidden px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 md:table-cell">Created</th>
                        <th class="hidden px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 lg:table-cell">Expires</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->jobs as $job)
                    <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="job-{{ $job->id }}">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon icon="document" class="size-4 text-zinc-400" />
                                </div>
                                <span class="max-w-[140px] truncate font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $job->original_name }}
                                </span>
                            </div>
                        </td>
                        <td class="hidden px-5 py-3.5 font-mono text-xs text-zinc-500 dark:text-zinc-400 sm:table-cell">
                            {{ $job->width }}×{{ $job->height }}px
                        </td>
                        <td class="px-5 py-3.5">
                            @php
                                $badge = match($job->status) {
                                    'completed'  => ['color' => 'green',  'label' => 'Completed'],
                                    'processing' => ['color' => 'yellow', 'label' => 'Processing'],
                                    'failed'     => ['color' => 'red',    'label' => 'Failed'],
                                    'expired'    => ['color' => 'zinc',   'label' => 'Expired'],
                                    default      => ['color' => 'zinc',   'label' => ucfirst($job->status)],
                                };
                            @endphp
                            <flux:badge :color="$badge['color']" size="sm">{{ $badge['label'] }}</flux:badge>
                        </td>
                        <td class="hidden px-5 py-3.5 text-xs text-zinc-400 dark:text-zinc-500 md:table-cell">
                            {{ $job->created_at->diffForHumans() }}
                        </td>
                        <td class="hidden px-5 py-3.5 text-xs text-zinc-400 dark:text-zinc-500 lg:table-cell">
                            @if($job->status === 'expired')
                                <span class="text-zinc-400 dark:text-zinc-600">Expired</span>
                            @else
                                {{ $job->expires_at?->format('M j, Y') ?? '—' }}
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            @if($job->status === 'completed' && $job->output_path)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-down-tray"
                                    :href="URL::signedRoute('jobs.download', $job)"
                                >
                                    Download
                                </flux:button>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-zinc-700">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
            {{ $this->jobs->links() }}
        </div>
        @endif
    </div>

    {{-- ── Spec strip ───────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach([
            ['CutContour spot colour', 'CMYK 0·100·0·0 — every output PDF', 'swatch'],
            ['Fully vector cut path', 'Never rasterised at any stage of the pipeline', 'scissors'],
            ['RIP-compatible output', 'Opens correctly in Illustrator, CorelDRAW, EFI, Caldera', 'printer'],
        ] as [$title, $body, $icon])
        <div class="flex items-start gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
            <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-pink-50 dark:bg-pink-950/30">
                <flux:icon :icon="$icon" class="size-4 text-cutcontour" />
            </div>
            <div>
                <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-zinc-400 dark:text-zinc-500">{{ $body }}</p>
            </div>
        </div>
        @endforeach
    </div>

</div>

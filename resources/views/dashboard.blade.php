<x-layouts::app :title="__('Dashboard')">
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
        // Placeholder counts — wire to CutJob model once migration runs
        $stats = [
            [
                'label'   => 'Total Jobs',
                'value'   => '0',
                'sub'     => 'All time',
                'icon'    => 'document-text',
                'color'   => 'text-zinc-400 dark:text-zinc-500',
                'bg'      => 'bg-zinc-100 dark:bg-zinc-800',
            ],
            [
                'label'   => 'Completed',
                'value'   => '0',
                'sub'     => 'Ready to download',
                'icon'    => 'check-circle',
                'color'   => 'text-emerald-500',
                'bg'      => 'bg-emerald-50 dark:bg-emerald-950/30',
            ],
            [
                'label'   => 'This Month',
                'value'   => '0',
                'sub'     => 'Jobs created',
                'icon'    => 'calendar',
                'color'   => 'text-cutcontour',
                'bg'      => 'bg-pink-50 dark:bg-pink-950/30',
            ],
            [
                'label'   => 'Storage Used',
                'value'   => '0 MB',
                'sub'     => 'Of allocated storage',
                'icon'    => 'archive-box',
                'color'   => 'text-amber-500',
                'bg'      => 'bg-amber-50 dark:bg-amber-950/30',
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($stats as $stat)
        <div class="flex items-start gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $stat['bg'] }}">
                <flux:icon :icon="$stat['icon']" class="size-5 {{ $stat['color'] }}" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
                    {{ $stat['label'] }}
                </p>
                <p class="mt-0.5 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $stat['value'] }}
                </p>
                <p class="text-xs text-zinc-400 dark:text-zinc-600 mt-0.5">
                    {{ $stat['sub'] }}
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
                <p class="text-xs text-zinc-400 dark:text-zinc-500">0 of 10 jobs used — Starter plan</p>
            </div>
            <flux:badge color="zinc" size="sm">Free</flux:badge>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-cutcontour transition-all duration-500" style="width: 0%"></div>
        </div>
        <div class="mt-2 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-600">
            <span>0 jobs used</span>
            <a href="{{ route('billing.edit') }}" wire:navigate
               class="text-cutcontour hover:underline font-medium">
                Upgrade plan →
            </a>
        </div>
    </div>

    {{-- ── Recent jobs ──────────────────────────────────────────── --}}
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Recent jobs</h2>
                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">Your last 20 processing jobs</p>
            </div>
            
        </div>

        @php $jobs = []; @endphp
        {{-- Replace with: auth()->user()->cutJobs()->latest()->take(20)->get() --}}

        @if(count($jobs) === 0)
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="mb-4 flex size-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                <svg width="24" height="24" viewBox="0 0 26 26" fill="none" class="text-zinc-400 dark:text-zinc-500">
                    <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                          stroke="#ec008c" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                    <rect x="7" y="7" width="12" height="12" rx="2.5" fill="#ec008c" opacity="0.4"/>
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No jobs yet</h3>
            <p class="text-sm text-zinc-400 dark:text-zinc-500 max-w-xs mb-5">
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
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 hidden sm:table-cell">Dimensions</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 hidden md:table-cell">Created</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500 hidden lg:table-cell">Expires</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($jobs as $job)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
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
                        <td class="px-5 py-3.5 text-zinc-500 dark:text-zinc-400 hidden sm:table-cell font-mono text-xs">
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
                        <td class="px-5 py-3.5 text-zinc-400 dark:text-zinc-500 text-xs hidden md:table-cell">
                            {{ $job->created_at->diffForHumans() }}
                        </td>
                        <td class="px-5 py-3.5 text-zinc-400 dark:text-zinc-500 text-xs hidden lg:table-cell">
                            @if($job->status === 'expired')
                                <span class="text-zinc-400 dark:text-zinc-600">Expired</span>
                            @else
                                {{ $job->expires_at->format('M j, Y') }}
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            @if($job->status === 'completed')
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray">
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
                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5 leading-relaxed">{{ $body }}</p>
            </div>
        </div>
        @endforeach
    </div>

</div>
</x-layouts::app>

<?php

use App\Models\CutJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System — Admin')] class extends Component {
    public string $cleanupOutput = '';

    public bool $cleanupRunning = false;

    #[Computed]
    public function storageStats(): array
    {
        $totalFiles = CutJob::whereNotNull('file_path')->count();
        $expiringSoon = CutJob::where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->whereNot('status', 'expired')
            ->count();
        $expired = CutJob::where('status', 'expired')->count();

        return [
            'total_files' => $totalFiles,
            'expiring_soon' => $expiringSoon,
            'expired_records' => $expired,
            'retention_days' => config('cutjob.retention_days'),
        ];
    }

    #[Computed]
    public function binaryChecks(): array
    {
        $binaries = config('cutjob.binaries', []);
        $results = [];

        foreach ($binaries as $name => $path) {
            $result = Process::run("which {$path} 2>/dev/null");
            $results[$name] = [
                'path' => $path,
                'available' => $result->successful(),
                'resolved' => trim($result->output()),
            ];
        }

        return $results;
    }

    #[Computed]
    public function queueStats(): array
    {
        $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $failedQueueJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();

        return [
            'pending' => $pendingJobs,
            'failed' => $failedQueueJobs,
        ];
    }

    #[Computed]
    public function configValues(): array
    {
        return [
            'max_file_size_mb' => config('cutjob.max_file_size_mb'),
            'confidence_threshold' => config('cutjob.confidence_threshold'),
            'retention_days' => config('cutjob.retention_days'),
            'queue_driver' => config('queue.default'),
            'storage_driver' => config('filesystems.default'),
        ];
    }

    public function runCleanup(): void
    {
        $this->cleanupRunning = true;
        $this->cleanupOutput = '';

        Artisan::call('cutjob:cleanup');
        $this->cleanupOutput = Artisan::output();

        $this->cleanupRunning = false;
        unset($this->storageStats);

        Flux::toast('Cleanup completed successfully.');
    }

    public function flushFailedQueueJobs(): void
    {
        Artisan::call('queue:flush');

        unset($this->queueStats);

        Flux::toast('Failed queue jobs flushed.');
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">System</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Pipeline health, storage cleanup, and configuration.</p>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('admin.dashboard')" wire:navigate icon="arrow-left">
            Back to Dashboard
        </flux:button>
    </div>

    {{-- ── Pipeline Health ─────────────────────────────────────── --}}
    <div>
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Pipeline Health</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach($this->binaryChecks as $name => $check)
                <div class="flex items-center gap-3 rounded-xl border px-4 py-3
                            {{ $check['available']
                                ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                                : 'border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20' }}">
                    @if($check['available'])
                        <flux:icon name="check-circle" class="size-5 shrink-0 text-emerald-500" />
                    @else
                        <flux:icon name="x-circle" class="size-5 shrink-0 text-red-500" />
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $name }}</p>
                        <p class="truncate text-[10px] text-zinc-500 dark:text-zinc-400">
                            {{ $check['available'] ? $check['resolved'] : 'Not found' }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Queue Status ────────────────────────────────────────── --}}
    <div>
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Queue Status</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Pending Jobs</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->queueStats['pending'] }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Failed Queue Jobs</p>
                <p class="mt-1 text-2xl font-bold {{ $this->queueStats['failed'] > 0 ? 'text-red-500' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ $this->queueStats['failed'] }}
                </p>
            </div>
            <div class="flex items-end rounded-xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                @if($this->queueStats['failed'] > 0)
                    <flux:button wire:click="flushFailedQueueJobs" variant="ghost" size="sm" icon="trash" class="text-red-500">
                        Flush Failed Jobs
                    </flux:button>
                @else
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">No failed queue jobs.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Storage Cleanup ─────────────────────────────────────── --}}
    <div>
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Storage Cleanup</h2>
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-4 px-5 py-4 sm:grid-cols-4">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Active Files</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->storageStats['total_files'] }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Expiring Soon (7d)</p>
                    <p class="mt-1 text-lg font-bold {{ $this->storageStats['expiring_soon'] > 0 ? 'text-amber-500' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $this->storageStats['expiring_soon'] }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Expired Records</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->storageStats['expired_records'] }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Retention</p>
                    <p class="mt-1 text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->storageStats['retention_days'] }} days</p>
                </div>
            </div>

            <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <div class="flex items-center gap-4">
                    <flux:button wire:click="runCleanup" variant="primary" size="sm" icon="trash" wire:loading.attr="disabled" wire:target="runCleanup">
                        <span wire:loading.remove wire:target="runCleanup">Run Cleanup Now</span>
                        <span wire:loading wire:target="runCleanup">Running…</span>
                    </flux:button>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">
                        Runs <code class="rounded bg-zinc-100 px-1 py-0.5 text-[10px] dark:bg-zinc-800">cutjob:cleanup</code> — deletes expired files and marks records.
                    </p>
                </div>

                @if($cleanupOutput)
                    <div class="mt-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-800">
                        <pre class="whitespace-pre-wrap text-xs text-zinc-700 dark:text-zinc-300">{{ $cleanupOutput }}</pre>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Configuration ───────────────────────────────────────── --}}
    <div>
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Configuration</h2>
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Setting</th>
                        <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @foreach($this->configValues as $key => $value)
                        <tr>
                            <td class="px-4 py-2.5 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                {{ str_replace('_', ' ', ucfirst($key)) }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-zinc-500 dark:text-zinc-400">
                                <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-[11px] dark:bg-zinc-800">{{ $value }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

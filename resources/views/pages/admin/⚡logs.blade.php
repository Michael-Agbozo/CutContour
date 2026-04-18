<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Error Logs — Admin')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public string $level = '';

    public int $lines = 100;

    public function updatedSearch(): void
    {
        $this->lines = 100;
    }

    public function updatedLevel(): void
    {
        $this->lines = 100;
    }

    public function loadMore(): void
    {
        $this->lines += 100;
    }

    public function clearLog(): void
    {
        $logPath = storage_path('logs/laravel.log');

        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        unset($this->entries);
        Flux::toast('Log file cleared.');
    }

    #[Computed]
    public function entries(): Collection
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath) || filesize($logPath) === 0) {
            return collect();
        }

        // Read the last N lines efficiently via a reverse file reader
        $lines = $this->tailFile($logPath, $this->lines * 5); // Over-read to compensate for multiline entries

        // Parse log entries (each entry starts with [YYYY-MM-DD HH:MM:SS])
        $entries = collect();
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+\S+\.(\w+):\s+(.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries->push($current);
                }
                $current = [
                    'timestamp' => $m[1],
                    'level' => strtolower($m[2]),
                    'message' => $m[3],
                    'stacktrace' => '',
                ];
            } elseif ($current !== null) {
                $current['stacktrace'] .= $line . "\n";
            }
        }

        if ($current !== null) {
            $entries->push($current);
        }

        // Filter out ProcessCutJob-related entries — those are visible in Failed Jobs
        $entries = $entries->reject(function (array $entry) {
            return str_contains($entry['message'], 'ProcessCutJob:')
                || str_contains($entry['message'], 'App\\Jobs\\ProcessCutJob');
        });

        // Filter by level
        if ($this->level !== '') {
            $entries = $entries->where('level', $this->level);
        }

        // Filter by search
        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $entries = $entries->filter(fn (array $e) =>
                str_contains(mb_strtolower($e['message']), $search)
                || str_contains(mb_strtolower($e['stacktrace']), $search)
            );
        }

        // Reverse so newest first, take requested amount
        return $entries->reverse()->take($this->lines)->values();
    }

    #[Computed]
    public function availableLevels(): array
    {
        return ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    }

    #[Computed]
    public function logFileSize(): string
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return '0 B';
        }

        $bytes = filesize($logPath);

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Read the last N lines of a file without loading it entirely into memory.
     *
     * @return array<int, string>
     */
    private function tailFile(string $path, int $count): array
    {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $count);
        $lines = [];

        $file->seek($start);
        while (! $file->eof()) {
            $line = rtrim($file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return $lines;
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Error Logs</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                Application logs excluding job-processing entries.
                <span class="ml-1 text-xs text-zinc-400 dark:text-zinc-500">({{ $this->logFileSize }})</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" size="sm" :href="route('admin.dashboard')" wire:navigate icon="arrow-left">
                Back to Dashboard
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search logs…" icon="magnifying-glass" size="sm" />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="level" size="sm">
                <option value="">All levels</option>
                @foreach($this->availableLevels as $lvl)
                    <option value="{{ $lvl }}">{{ ucfirst($lvl) }}</option>
                @endforeach
            </flux:select>
            <flux:button wire:click="clearLog" wire:confirm="Are you sure? This will permanently clear the log file." variant="ghost" size="sm" icon="trash" class="text-red-500">
                Clear
            </flux:button>
        </div>
    </div>

    {{-- Log entries --}}
    @if($this->entries->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
            <flux:icon name="document-text" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">No log entries found.</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                @if($this->search || $this->level)
                    Try adjusting your filters.
                @else
                    The log file is empty or contains only job-processing entries.
                @endif
            </p>
        </div>
    @else
        <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach($this->entries as $entry)
                @php
                    $levelColors = [
                        'emergency' => 'bg-red-600 text-white',
                        'alert'     => 'bg-red-500 text-white',
                        'critical'  => 'bg-red-500 text-white',
                        'error'     => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
                        'warning'   => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                        'notice'    => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
                        'info'      => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
                        'debug'     => 'bg-zinc-50 text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-500',
                    ];
                    $color = $levelColors[$entry['level']] ?? 'bg-zinc-100 text-zinc-600';
                @endphp
                <details class="group">
                    <summary class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <span class="mt-0.5 inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $color }}">
                            {{ $entry['level'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs text-zinc-800 dark:text-zinc-200">{{ Str::limit($entry['message'], 200) }}</p>
                            <p class="mt-0.5 text-[10px] text-zinc-400 dark:text-zinc-500">{{ $entry['timestamp'] }}</p>
                        </div>
                        <flux:icon name="chevron-down" class="mt-1 size-4 shrink-0 text-zinc-400 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="border-t border-zinc-100 bg-zinc-50/50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/30">
                        <p class="whitespace-pre-wrap break-all text-xs leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $entry['message'] }}</p>
                        @if(trim($entry['stacktrace']))
                            <div class="mt-2 max-h-64 overflow-auto rounded-lg border border-zinc-200 bg-zinc-100 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                <pre class="whitespace-pre-wrap break-all text-[11px] leading-relaxed text-zinc-600 dark:text-zinc-400">{{ trim($entry['stacktrace']) }}</pre>
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>

        @if($this->entries->count() >= $this->lines)
            <div class="flex justify-center">
                <flux:button wire:click="loadMore" variant="ghost" size="sm">
                    Load more…
                </flux:button>
            </div>
        @endif
    @endif

</div>

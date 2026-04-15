<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('New Job')] class extends Component {
    use WithFileUploads;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    /** @var string  idle | uploading | processing | completed | failed */
    public string $state = 'idle';

    public string $errorMessage = '';

    /** Populated once the backend pipeline completes (PRD §13) */
    public ?string $outputFilename = null;
    public ?int $outputWidth = null;
    public ?int $outputHeight = null;
    public ?int $processingMs = null;
    public bool $aiUsed = false;

    /** Dimension display unit */
    public string $unit = 'in';

    /** Contour offset */
    public string $offsetValue = '+0.125';

    /** Spot color — customisable per print house (PRD §8) */
    public string $spotColorName = 'CutContour';

    /** @var int<0,100> */
    public int $spotColorC = 0;

    /** @var int<0,100> */
    public int $spotColorM = 100;

    /** @var int<0,100> */
    public int $spotColorY = 0;

    /** @var int<0,100> */
    public int $spotColorK = 0;

    /** @var array<int,array{label:string,done:bool,active:bool}> */
    public array $steps = [];

    public function mount(): void
    {
        $this->resetSteps();
    }

    /**
     * Called by Livewire after the temporary file upload completes.
     * Validates per PRD §6 then moves to processing state.
     */
    public function updatedFile(): void
    {
        $this->validate([
            'file' => [
                'required',
                'file',
                'max:102400', // 100 MB — PRD §6
                'mimes:jpg,jpeg,png,svg,pdf,ai',
            ],
        ]);

        $this->state = 'processing';
        $this->resetSteps();

        // TODO: dispatch ProcessCutJob once the pipeline services are built.
        // ConfidenceService decides Fast vs AI-Enhanced path (PRD §7).
    }

    public function removeFile(): void
    {
        $this->reset('file', 'errorMessage', 'outputFilename', 'outputWidth', 'outputHeight', 'processingMs', 'aiUsed');
        $this->state = 'idle';
        $this->resetSteps();
    }

    /**
     * Convert px to the selected display unit.
     * Screen-standard: 96 px per inch.
     */
    public function formatDimension(?int $px): string
    {
        if ($px === null) {
            return '—';
        }

        return match ($this->unit) {
            'in' => number_format($px / 96, 2),
            'cm' => number_format($px / 96 * 2.54, 2),
            'mm' => number_format($px / 96 * 25.4, 1),
            'pt' => number_format($px / 96 * 72, 1),
            'px' => (string) $px,
            default => number_format($px / 96, 2),
        };
    }

    /**
     * Approximate RGB hex for the spot color swatch.
     * Uses standard CMYK→RGB formula; not ICC-accurate but correct for display.
     */
    #[Computed]
    public function spotColorHex(): string
    {
        $r = (int) round(255 * (1 - $this->spotColorC / 100) * (1 - $this->spotColorK / 100));
        $g = (int) round(255 * (1 - $this->spotColorM / 100) * (1 - $this->spotColorK / 100));
        $b = (int) round(255 * (1 - $this->spotColorY / 100) * (1 - $this->spotColorK / 100));

        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );
    }

    private function resetSteps(): void
    {
        $this->steps = [
            ['label' => 'Uploading file',                  'done' => false, 'active' => false],
            ['label' => 'Preprocessing (ImageMagick)',     'done' => false, 'active' => false],
            ['label' => 'Confidence check',                'done' => false, 'active' => false],
            ['label' => 'Vectorising (Potrace)',            'done' => false, 'active' => false],
            ['label' => 'Assembling CutContour layer',     'done' => false, 'active' => false],
            ['label' => 'Exporting PDF',                   'done' => false, 'active' => false],
        ];
    }
};

?>

{{--
    Full-bleed canvas layout.
    Livewire auto-applies layouts::app (sidebar) via component_layout config.
    -m-6 lg:-m-8 cancels flux:main padding so the canvas bleeds edge-to-edge.
--}}
<div class="-m-6 flex flex-col overflow-hidden lg:-m-8 h-[calc(100dvh-4rem)] lg:h-dvh">

    {{-- ── Top status bar ─────────────────────────────────── --}}
    <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center gap-3">
            <flux:button size="sm" variant="ghost" :href="route('dashboard')" wire:navigate icon="arrow-left">
                Dashboard
            </flux:button>
            <span class="h-4 w-px bg-zinc-200 dark:bg-zinc-700"></span>
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">New Job</span>
        </div>

        <div class="flex items-center gap-2">
            @if($state === 'idle')
                <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                    <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                    Waiting for artwork
                </span>
            @elseif($state === 'uploading')
                <span class="flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                    <span class="size-1.5 animate-pulse rounded-full bg-blue-500"></span>
                    Uploading…
                </span>
            @elseif($state === 'processing')
                <span class="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                    <span class="size-1.5 animate-pulse rounded-full bg-amber-500"></span>
                    Processing…
                </span>
            @elseif($state === 'completed')
                <span class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Ready to export
                </span>
            @elseif($state === 'failed')
                <span class="flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                    <span class="size-1.5 rounded-full bg-red-500"></span>
                    Processing failed
                </span>
            @endif
        </div>
    </div>

    {{-- ── Main two-column area ────────────────────────────── --}}
    <div class="flex min-h-0 flex-1 overflow-hidden">

        {{-- ── Canvas panel (left) ─────────────────────────── --}}
        <div class="canvas-dots relative flex flex-1 flex-col items-center justify-center overflow-auto bg-zinc-100 dark:bg-zinc-950">

            @if($state === 'idle')
            <div class="pointer-events-none flex select-none flex-col items-center gap-3">
                <div class="relative rounded-lg bg-white shadow-2xl ring-1 ring-zinc-900/10 dark:bg-zinc-900 dark:ring-white/5"
                     style="width: 280px; height: 360px;">
                    <div class="absolute inset-5 rounded-lg" style="border: 1.5px dashed {{ $this->spotColorHex }}; opacity: 0.25;"></div>
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-8 text-center">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                            <svg viewBox="0 0 26 26" fill="none" class="size-6 text-zinc-300 dark:text-zinc-600">
                                <rect x="1.5" y="1.5" width="23" height="23" rx="4"
                                      stroke="currentColor" stroke-width="1.5" stroke-dasharray="4.5 2.5"/>
                                <rect x="7" y="7" width="12" height="12" rx="2.5" fill="currentColor"/>
                            </svg>
                        </div>
                        <p class="text-xs leading-relaxed text-zinc-300 dark:text-zinc-600">
                            Upload artwork to preview the cut path
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 font-mono text-[10px] text-zinc-400 dark:text-zinc-600">
                    <span class="h-px w-10 bg-zinc-300 dark:bg-zinc-700"></span>
                    no file selected
                    <span class="h-px w-10 bg-zinc-300 dark:bg-zinc-700"></span>
                </div>
            </div>

            @elseif($state === 'uploading' || $state === 'processing')
            <div class="mx-4 w-full max-w-sm rounded-2xl border border-zinc-200 bg-white p-7 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-5 flex items-center gap-2.5">
                    <svg class="size-4 shrink-0 animate-spin text-cutcontour" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $state === 'uploading' ? 'Uploading file…' : 'Generating cut path…' }}
                    </span>
                </div>

                <div class="space-y-0.5">
                    @foreach($steps as $step)
                    <div class="flex items-center gap-3 rounded-lg px-2 py-2
                                {{ $step['active'] ? 'bg-pink-50 dark:bg-pink-950/20' : '' }}">
                        <div class="flex size-5 shrink-0 items-center justify-center">
                            @if($step['done'])
                                <svg class="size-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            @elseif($step['active'])
                                <svg class="size-4 animate-spin text-cutcontour" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                    <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            @else
                                <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                            @endif
                        </div>
                        <span class="text-xs
                                     {{ $step['active'] ? 'font-semibold text-zinc-900 dark:text-zinc-100' : '' }}
                                     {{ $step['done']   ? 'text-zinc-400 line-through dark:text-zinc-600' : 'text-zinc-500 dark:text-zinc-500' }}">
                            {{ $step['label'] }}
                        </span>
                    </div>
                    @endforeach
                </div>

                @php
                    $done = collect($steps)->where('done', true)->count();
                    $pct  = count($steps) > 0 ? round(($done / count($steps)) * 100) : 0;
                @endphp
                <div class="mt-5 h-1 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-cutcontour transition-all duration-700" style="width: {{ $pct }}%"></div>
                </div>
            </div>

            @elseif($state === 'completed')
            <div class="relative p-14">

                {{-- Vertical ruler --}}
                <div class="absolute bottom-14 left-5 top-14 flex items-center justify-center">
                    <div class="relative h-full w-0">
                        <div class="absolute inset-y-0 left-0 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                        <div class="absolute left-0 top-0 h-2 w-px -translate-y-1 bg-zinc-400 dark:bg-zinc-500"></div>
                        <div class="absolute bottom-0 left-0 h-2 w-px translate-y-1 bg-zinc-400 dark:bg-zinc-500"></div>
                        <div class="absolute left-0 top-1/2 -translate-x-5 -translate-y-1/2 -rotate-90 whitespace-nowrap font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                            {{ $this->formatDimension($outputHeight) }} {{ $unit }}
                        </div>
                    </div>
                </div>

                {{-- Page card --}}
                <div class="relative overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-zinc-900/10 dark:bg-zinc-900 dark:ring-white/5"
                     style="min-width: 240px; min-height: 300px;">
                    @if($file)
                        <img src="{{ $file->temporaryUrl() }}"
                             alt="Uploaded artwork"
                             class="block max-h-[55vh] w-full object-contain" />
                    @endif

                    {{-- Dashed cut-path border — uses custom spot colour --}}
                    <div class="pointer-events-none absolute inset-5 rounded-xl"
                         style="border: 2px dashed {{ $this->spotColorHex }}; box-shadow: 0 0 24px {{ $this->spotColorHex }}30;">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-white shadow-lg"
                             style="background: {{ $this->spotColorHex }};">
                            {{ $spotColorName }} · C:{{ $spotColorC }} M:{{ $spotColorM }} Y:{{ $spotColorY }} K:{{ $spotColorK }}
                        </div>
                    </div>
                </div>

                {{-- Horizontal ruler --}}
                <div class="absolute bottom-5 left-14 right-14 flex items-center">
                    <div class="relative w-full">
                        <div class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-zinc-300 dark:bg-zinc-700"></div>
                        <div class="absolute left-0 top-1/2 h-2 w-px -translate-y-1/2 bg-zinc-400 dark:bg-zinc-500"></div>
                        <div class="absolute right-0 top-1/2 h-2 w-px -translate-y-1/2 bg-zinc-400 dark:bg-zinc-500"></div>
                        <div class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-full pb-1 whitespace-nowrap font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                            {{ $this->formatDimension($outputWidth) }} {{ $unit }}
                        </div>
                    </div>
                </div>

            </div>

            @elseif($state === 'failed')
            <div class="mx-4 flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-900/40 dark:bg-red-950/20">
                <div class="flex size-12 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon icon="exclamation-triangle" class="size-6 text-red-500" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-red-900 dark:text-red-200">Processing failed</h3>
                    <p class="mt-1 text-xs leading-relaxed text-red-700 dark:text-red-400">
                        {{ $errorMessage ?: 'Processing failed. This may be due to file complexity. Try a simpler or higher-contrast version.' }}
                    </p>
                </div>
                <flux:button wire:click="removeFile" variant="primary" size="sm" icon="arrow-path">
                    Try again
                </flux:button>
            </div>
            @endif

        </div>{{-- /canvas --}}

        {{-- ── Right config panel ──────────────────────────── --}}
        <div class="flex w-80 shrink-0 flex-col overflow-y-auto border-l border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

            <div class="shrink-0 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Job Configuration</h2>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">Setup artwork for production routing.</p>
            </div>

            <div class="flex flex-1 flex-col gap-5 px-5 py-5">

                {{-- ── Source Artwork ────────────────────── --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        Source Artwork
                    </p>

                    @if($state === 'idle')
                    <div
                        x-data="{
                            active: false,
                            handleDrop(e) {
                                this.active = false;
                                const files = e.dataTransfer.files;
                                if (!files.length) return;
                                const dt = new DataTransfer();
                                dt.items.add(files[0]);
                                this.$refs.picker.files = dt.files;
                                this.$refs.picker.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }"
                        x-on:dragover.prevent="active = true"
                        x-on:dragleave.prevent="active = false"
                        x-on:drop.prevent="handleDrop($event)"
                        :class="active
                            ? 'border-cutcontour bg-pink-50/60 dark:bg-pink-950/10'
                            : 'border-zinc-200 dark:border-zinc-700 hover:border-cutcontour hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                        class="group relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition-all duration-150"
                        @click="$refs.picker.click()"
                    >
                        <input
                            x-ref="picker"
                            type="file"
                            accept=".jpg,.jpeg,.png,.svg,.pdf,.ai"
                            class="sr-only"
                            wire:model="file"
                        />

                        {{-- Livewire upload-in-progress overlay --}}
                        <div wire:loading wire:target="file"
                             class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/90 dark:bg-zinc-900/90">
                            <svg class="size-5 animate-spin text-cutcontour" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Uploading…</span>
                        </div>

                        <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 transition-colors dark:bg-zinc-800 group-hover:bg-pink-50 dark:group-hover:bg-pink-950/20">
                            <svg class="size-4 text-zinc-400 transition-colors group-hover:text-cutcontour" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Drag &amp; drop file here</p>
                            <p class="mt-0.5 text-[10px] text-zinc-400 dark:text-zinc-500">
                                PNG, JPG, SVG, PDF, AI — up to 100 MB
                            </p>
                        </div>
                    </div>

                    @error('file')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    @else
                    <div class="flex items-center gap-2.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-700 dark:ring-zinc-600">
                            <flux:icon icon="document" class="size-4 text-zinc-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            @if($file)
                            <p class="truncate text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $file->getClientOriginalName() }}</p>
                            <p class="text-[10px] text-zinc-400 dark:text-zinc-500">{{ number_format($file->getSize() / 1048576, 2) }} MB</p>
                            @endif
                        </div>
                        @if($state !== 'processing' && $state !== 'uploading')
                        <button wire:click="removeFile"
                                class="shrink-0 text-zinc-400 transition-colors hover:text-red-500">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- ── Target Dimensions ─────────────────── --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        Target Dimensions
                    </p>
                    <div class="flex gap-2">
                        <div class="flex flex-1 items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <span class="shrink-0 text-[10px] font-bold text-zinc-400">W</span>
                            <span class="flex-1 text-center font-mono text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $this->formatDimension($outputWidth) }}
                            </span>
                        </div>
                        <div class="flex flex-1 items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <span class="shrink-0 text-[10px] font-bold text-zinc-400">H</span>
                            <span class="flex-1 text-center font-mono text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $this->formatDimension($outputHeight) }}
                            </span>
                        </div>
                        {{-- Unit selector --}}
                        <select wire:model.live="unit"
                                class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-2 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300 focus:outline-none focus:ring-2 focus:ring-cutcontour/40 cursor-pointer">
                            <option value="in">in</option>
                            <option value="cm">cm</option>
                            <option value="mm">mm</option>
                            <option value="pt">pt</option>
                            <option value="px">px</option>
                        </select>
                    </div>
                </div>

                {{-- ── Contour Cutline ────────────────────── --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                            Contour Cutline
                        </p>
                        <button type="button" class="text-[10px] font-semibold text-cutcontour hover:underline">
                            Auto-detect
                        </button>
                    </div>
                    <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <span class="text-xs text-zinc-400">Offset</span>
                        <span class="ml-auto font-mono text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $offsetValue }} {{ $unit }}
                        </span>
                    </div>
                </div>

                {{-- ── Spot Colour ────────────────────────── --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                            Spot Colour
                        </p>
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-600">for RIP software</span>
                    </div>

                    <div class="space-y-2.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3.5 dark:border-zinc-700 dark:bg-zinc-800/40">

                        {{-- Colour name --}}
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                Name
                            </label>
                            <input
                                type="text"
                                wire:model.live="spotColorName"
                                placeholder="e.g. CutContour, Die Cut, Thru-cut"
                                maxlength="40"
                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-800 placeholder-zinc-300 focus:border-cutcontour/50 focus:outline-none focus:ring-2 focus:ring-cutcontour/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:placeholder-zinc-600"
                            />
                        </div>

                        {{-- CMYK values --}}
                        <div>
                            <label class="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                CMYK Values (0 – 100)
                            </label>
                            <div class="grid grid-cols-4 gap-1.5">
                                @foreach([
                                    ['label' => 'C', 'field' => 'spotColorC'],
                                    ['label' => 'M', 'field' => 'spotColorM'],
                                    ['label' => 'Y', 'field' => 'spotColorY'],
                                    ['label' => 'K', 'field' => 'spotColorK'],
                                ] as $ch)
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500">{{ $ch['label'] }}</span>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        wire:model.blur="{{ $ch['field'] }}"
                                        class="w-full rounded-lg border border-zinc-200 bg-white px-1 py-1.5 text-center text-xs font-mono font-semibold text-zinc-800 focus:border-cutcontour/50 focus:outline-none focus:ring-2 focus:ring-cutcontour/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                    />
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Live swatch — Alpine computes hex from entangled CMYK --}}
                        <div x-data="{
                            c: $wire.entangle('spotColorC'),
                            m: $wire.entangle('spotColorM'),
                            y: $wire.entangle('spotColorY'),
                            k: $wire.entangle('spotColorK'),
                            name: $wire.entangle('spotColorName'),
                            get hex() {
                                const clamp = v => Math.max(0, Math.min(255, v));
                                const r = clamp(Math.round(255 * (1 - this.c/100) * (1 - this.k/100)));
                                const g = clamp(Math.round(255 * (1 - this.m/100) * (1 - this.k/100)));
                                const b = clamp(Math.round(255 * (1 - this.y/100) * (1 - this.k/100)));
                                return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
                            }
                        }">
                            <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/60">
                                <span class="size-3.5 shrink-0 rounded-sm shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-600 transition-colors"
                                      :style="`background: ${hex}`"></span>
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-200 truncate"
                                      x-text="name || 'Unnamed'"></span>
                                <span class="ml-auto shrink-0 font-mono text-[10px] text-zinc-400 dark:text-zinc-500"
                                      x-text="`C:${c} M:${m} Y:${y} K:${k}`"></span>
                            </div>
                        </div>

                        <p class="text-[10px] leading-relaxed text-zinc-400 dark:text-zinc-600">
                            This name and CMYK will be used as the spot colour in the exported PDF, making it selectable in Illustrator, CorelDRAW, and RIP software.
                        </p>
                    </div>
                </div>

                <div class="flex-1"></div>

            </div>

            {{-- Action buttons — pinned to bottom --}}
            <div class="shrink-0 space-y-2.5 border-t border-zinc-100 p-5 dark:border-zinc-800">

                @if($state === 'completed')
                    <flux:button variant="primary" icon="arrow-down-tray" class="w-full">
                        Download PDF
                    </flux:button>
                    <button wire:click="removeFile"
                            class="w-full rounded-lg py-2 text-xs text-zinc-400 transition-colors hover:text-zinc-600 dark:hover:text-zinc-300">
                        Process another file
                    </button>

                @elseif($state === 'uploading' || $state === 'processing')
                    <button disabled
                        class="flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-zinc-100 px-4 py-3 text-sm font-medium text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                            <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ $state === 'uploading' ? 'Uploading…' : 'Processing…' }}
                    </button>

                @elseif($state === 'failed')
                    <flux:button wire:click="removeFile" variant="primary" icon="arrow-path" class="w-full">
                        Try Again
                    </flux:button>

                @else
                    <button type="button" disabled
                        class="w-full cursor-not-allowed rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm font-medium text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500">
                        Preview Vector Data
                    </button>
                    <button type="button" disabled
                        class="w-full cursor-not-allowed rounded-xl bg-cutcontour px-4 py-2.5 text-sm font-semibold text-white opacity-40">
                        Generate Print File
                    </button>
                @endif

            </div>
        </div>{{-- /right panel --}}

    </div>{{-- /two-column --}}

</div>

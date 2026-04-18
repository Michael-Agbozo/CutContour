@props([
    'label',
    'value',
    'icon' => null,
    'iconColor' => 'text-zinc-500',
    'iconBg' => 'bg-zinc-100 dark:bg-zinc-800',
    'valueClass' => 'text-zinc-900 dark:text-zinc-100',
])

<div class="rounded-xl border border-zinc-200 bg-white px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900">
    @if($icon)
        <div class="flex items-center gap-3">
            <div class="flex size-9 items-center justify-center rounded-lg {{ $iconBg }}">
                <flux:icon :name="$icon" class="size-4.5 {{ $iconColor }}" />
            </div>
            <div>
                <p class="text-lg font-semibold {{ $valueClass }}">{{ $value }}</p>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
            </div>
        </div>
    @else
        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold {{ $valueClass }}">{{ $value }}</p>
    @endif
</div>

@props([
    'action',
    'label' => null,
    'prompt' => 'Sure?',
    'icon' => 'trash',
    'variant' => 'ghost',
    'size' => 'sm',
    'destructive' => true,
])

<div x-data="{ confirm: false }" class="flex items-center gap-1">
    @if($label)
        <flux:button
            x-show="! confirm"
            @click="confirm = true"
            :variant="$variant"
            :size="$size"
            :icon="$icon"
            @class(['text-red-500 hover:text-red-600' => $destructive])
        >
            {{ $label }}
        </flux:button>
    @else
        <button
            x-show="! confirm"
            @click="confirm = true"
            @class([
                'rounded p-1 opacity-0 transition-all group-hover:opacity-100',
                'text-zinc-300 hover:bg-red-50 hover:text-red-500 dark:text-zinc-600 dark:hover:bg-red-950/30 dark:hover:text-red-400' => $destructive,
                'text-zinc-300 hover:bg-zinc-100 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-400' => ! $destructive,
            ])
            title="{{ $icon === 'trash' ? 'Delete' : ucfirst($icon) }}"
        >
            <flux:icon :name="$icon" class="size-3.5" />
        </button>
    @endif

    <div
        x-show="confirm"
        x-cloak
        x-on:click.outside="confirm = false"
        @class([
            'flex items-center gap-1.5 rounded-lg border px-2 py-1 shadow-sm',
            'border-red-200 bg-white dark:border-red-900/40 dark:bg-zinc-900' => $destructive,
            'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => ! $destructive,
        ])
    >
        <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">{{ $prompt }}</span>
        <button
            @click="{{ $action }}; confirm = false"
            @class([
                'rounded px-1.5 py-0.5 text-[10px] font-semibold',
                'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30' => $destructive,
                'text-cutcontour hover:bg-pink-50 dark:hover:bg-pink-950/30' => ! $destructive,
            ])
        >Yes</button>
        <button
            @click="confirm = false"
            class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
        >No</button>
    </div>
</div>

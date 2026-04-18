@props([
    'status',
])

@php
    $classes = match ($status) {
        'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'processing' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        'expired' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500',
        'admin' => 'bg-cutcontour/10 text-cutcontour',
        default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {$classes}"]) }}>
    {{ ucfirst($status) }}
</span>

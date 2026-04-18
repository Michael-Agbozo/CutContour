@props([
    'title',
    'description' => null,
    'backRoute' => null,
    'backLabel' => 'Back to Dashboard',
])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</h1>
        @if($description)
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif
    </div>

    @if($backRoute)
        <flux:button variant="ghost" size="sm" :href="$backRoute" wire:navigate icon="arrow-left">
            {{ $backLabel }}
        </flux:button>
    @elseif(isset($actions))
        {{ $actions }}
    @endif
</div>

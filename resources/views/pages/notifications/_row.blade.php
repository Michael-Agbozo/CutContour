{{--
    Shared notification row for both unread and read sections.
    Expects: $notification, $data, $isOk, $payload
--}}
@php $isRead = $notification->read_at !== null; @endphp

<div
    class="group relative flex cursor-pointer items-start gap-4 px-5 py-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
    wire:key="notification-{{ $notification->id }}"
    @click="openNotification({{ $payload }})"
>
    {{-- Status icon --}}
    <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full
                {{ $isOk ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
        @if($isOk)
            <svg class="size-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
        @else
            <svg class="size-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        @endif
    </div>

    {{-- Content --}}
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
            {{ $data['original_name'] ?? 'Unknown file' }}
        </p>
        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-500">
            {{ $isOk ? 'Processed successfully' : 'Processing failed' }}
            · {{ $notification->created_at->diffForHumans() }}
        </p>
    </div>

    {{-- Right: read-toggle + delete --}}
    <div class="flex shrink-0 items-center gap-2" @click.stop>

        {{-- Read / unread toggle --}}
        <button
            wire:click="toggleRead('{{ $notification->id }}')"
            title="{{ $isRead ? 'Mark as unread' : 'Mark as read' }}"
            class="flex size-7 items-center justify-center rounded-lg transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
        >
            @if($isRead)
                {{-- Hollow circle = read, click to mark unread --}}
                <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <circle cx="12" cy="12" r="9"/>
                </svg>
            @else
                {{-- Filled dot = unread, click to mark read --}}
                <span class="size-2.5 rounded-full bg-cutcontour"></span>
            @endif
        </button>

        {{-- Delete — idle: trash icon (hover); confirming: "Sure? Yes / No" --}}
        <div class="flex items-center">
            <template x-if="pendingDeleteId !== '{{ $notification->id }}'">
                <button
                    @click.stop="pendingDeleteId = '{{ $notification->id }}'"
                    class="flex size-7 items-center justify-center rounded-lg text-zinc-300 opacity-0 transition-all hover:bg-red-50 hover:text-red-500 group-hover:opacity-100 dark:text-zinc-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                    title="Delete notification"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                </button>
            </template>
            <template x-if="pendingDeleteId === '{{ $notification->id }}'">
                <div class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2 py-1 shadow-sm dark:border-red-900/40 dark:bg-zinc-900">
                    <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">Sure?</span>
                    <button
                        @click.stop="$wire.deleteNotification('{{ $notification->id }}'); pendingDeleteId = null"
                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                    >Yes</button>
                    <button
                        @click.stop="pendingDeleteId = null"
                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >No</button>
                </div>
            </template>
        </div>

    </div>
</div>

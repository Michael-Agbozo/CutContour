@blaze(fold: true, safe: ['position'])

@props([
    'position' => 'bottom end',
])

<ui-toast x-data x-on:toast-show.document="! $el.closest('ui-toast-group') && $el.showToast($event.detail)" popover="manual" position="{{ $position }}" wire:ignore>
    <template>
        <div {{ $attributes->only(['class'])->class('max-w-sm in-[ui-toast-group]:max-w-auto in-[ui-toast-group]:w-xs sm:in-[ui-toast-group]:w-sm') }} data-variant="" data-flux-toast-dialog>

            {{-- Success --}}
            <div class="hidden [[data-flux-toast-dialog][data-variant=success]_&]:flex items-start gap-3 bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800 rounded-lg p-4 shadow-lg text-sm text-green-800 dark:text-green-300" role="alert">
                <svg class="shrink-0 mt-0.5 size-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="hidden empty:hidden font-semibold [&:not(:empty)]:block [&:not(:empty)+div]:mt-0.5"><slot name="heading"></slot></div>
                    <div class="leading-5"><slot name="text"></slot></div>
                </div>
                <ui-close>
                    <button type="button" class="shrink-0 text-green-500 hover:text-green-700 dark:hover:text-green-200">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </ui-close>
            </div>

            {{-- Danger --}}
            <div class="hidden [[data-flux-toast-dialog][data-variant=danger]_&]:flex items-start gap-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-lg p-4 shadow-lg text-sm text-red-800 dark:text-red-300" role="alert">
                <svg class="shrink-0 mt-0.5 size-5 text-red-500 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="hidden empty:hidden font-semibold [&:not(:empty)]:block [&:not(:empty)+div]:mt-0.5"><slot name="heading"></slot></div>
                    <div class="leading-5"><slot name="text"></slot></div>
                </div>
                <ui-close>
                    <button type="button" class="shrink-0 text-red-400 hover:text-red-600 dark:hover:text-red-200">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </ui-close>
            </div>

            {{-- Warning --}}
            <div class="hidden [[data-flux-toast-dialog][data-variant=warning]_&]:flex items-start gap-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg p-4 shadow-lg text-sm text-amber-800 dark:text-amber-300" role="alert">
                <svg class="shrink-0 mt-0.5 size-5 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="hidden empty:hidden font-semibold [&:not(:empty)]:block [&:not(:empty)+div]:mt-0.5"><slot name="heading"></slot></div>
                    <div class="leading-5"><slot name="text"></slot></div>
                </div>
                <ui-close>
                    <button type="button" class="shrink-0 text-amber-500 hover:text-amber-700 dark:hover:text-amber-200">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </ui-close>
            </div>

            {{-- Default (no variant) --}}
            <div class="[[data-flux-toast-dialog]:not([data-variant=success]):not([data-variant=danger]):not([data-variant=warning])_&]:flex hidden items-start gap-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 shadow-lg text-sm text-zinc-800 dark:text-zinc-200" role="alert">
                <svg class="shrink-0 mt-0.5 size-5 text-zinc-400 dark:text-zinc-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <div class="hidden empty:hidden font-semibold [&:not(:empty)]:block [&:not(:empty)+div]:mt-0.5"><slot name="heading"></slot></div>
                    <div class="leading-5"><slot name="text"></slot></div>
                </div>
                <ui-close>
                    <button type="button" class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </ui-close>
            </div>

        </div>
    </template>
</ui-toast>

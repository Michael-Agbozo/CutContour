<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

    #[Computed]
    public function unread(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->unreadNotifications()->latest()->get();
    }

    public function markAsRead(string $id): void
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
            $this->dispatch('notification-count-changed');
        }
    }

    public function toggleRead(string $id): void
    {
        $notification = auth()->user()->notifications()->find($id);

        if (! $notification) {
            return;
        }

        if ($notification->read_at) {
            $notification->forceFill(['read_at' => null])->save();
        } else {
            $notification->markAsRead();
        }

        unset($this->unread);
        $this->dispatch('notification-count-changed');
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unread);
        $this->dispatch('notification-count-changed');
    }

    public function deleteNotification(string $id): void
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification) {
            $wasUnread = ! $notification->read_at;
            $notification->delete();
            unset($this->unread);

            if ($wasUnread) {
                $this->dispatch('notification-count-changed');
            }
        }
    }
};

?>

<div
    class="mx-auto max-w-2xl space-y-8 px-4 py-8"
    x-data="{
        modal: false,
        active: null,
        pendingDeleteId: null,
        pendingModalDelete: false,
        openNotification(data) {
            this.pendingDeleteId    = null;
            this.pendingModalDelete = false;
            this.active = data;
            this.modal  = true;
            $wire.markAsRead(data.id);
        },
        closeModal() {
            this.pendingModalDelete = false;
            this.modal = false;
            setTimeout(() => { this.active = null; }, 200);
        },
        deleteActive() {
            $wire.deleteNotification(this.active.id);
            this.closeModal();
        }
    }"
    @keydown.escape.window="closeModal()"
>

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Notifications</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">All job updates and alerts.</p>
        </div>
        @if($this->unread->isNotEmpty())
            <flux:button wire:click="markAllAsRead" variant="ghost" size="sm">
                Mark all as read
            </flux:button>
        @endif
    </div>

    {{-- ── Unread section ──────────────────────────────────────────── --}}
    <div>
        <div class="mb-3 flex items-center gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Unread</h2>
            @if($this->unread->isNotEmpty())
                <span class="rounded-full bg-cutcontour px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                    {{ $this->unread->count() }}
                </span>
            @endif
        </div>

        @if($this->unread->isEmpty())
            <div class="flex items-center gap-3 rounded-xl border border-zinc-100 bg-zinc-50 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </div>
                <p class="text-sm text-zinc-400 dark:text-zinc-500">You're all caught up.</p>
            </div>
        @else
            <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
                @foreach($this->unread as $notification)
                    @php
                        $data    = $notification->data;
                        $isOk    = ($data['status'] ?? '') === 'completed';
                        $isAdmin = auth()->user()->is_admin;
                        $payload = \Illuminate\Support\Js::from([
                            'id'           => $notification->id,
                            'isOk'         => $isOk,
                            'originalName' => $data['original_name'] ?? 'Unknown file',
                            'status'       => $data['status'] ?? '',
                            'downloadUrl'  => $data['download_url'] ?? null,
                            'errorMessage' => $isAdmin
                                ? ($data['error_message'] ?? null)
                                : 'Processing failed. Please try again or contact support.',
                            'createdAt'    => $notification->created_at->format('M j, Y \a\t g:i A'),
                            'age'          => $notification->created_at->diffForHumans(),
                        ]);
                    @endphp
                    @include('pages.notifications._row', compact('notification', 'data', 'isOk', 'payload'))
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Read section ────────────────────────────────────────────── --}}
    @php $readPaginator = auth()->user()->readNotifications()->latest()->paginate(15, pageName: 'read_page'); @endphp
    @if($readPaginator->total() > 0)
    <div>
        <div class="mb-3 flex items-center gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Read</h2>
            <span class="text-[10px] text-zinc-400 dark:text-zinc-600">{{ $readPaginator->total() }}</span>
        </div>

        <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white opacity-70 dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach($readPaginator as $notification)
                @php
                    $data    = $notification->data;
                    $isOk    = ($data['status'] ?? '') === 'completed';
                    $isAdmin = auth()->user()->is_admin;
                    $payload = \Illuminate\Support\Js::from([
                        'id'           => $notification->id,
                        'isOk'         => $isOk,
                        'originalName' => $data['original_name'] ?? 'Unknown file',
                        'status'       => $data['status'] ?? '',
                        'downloadUrl'  => $data['download_url'] ?? null,
                        'errorMessage' => $isAdmin
                            ? ($data['error_message'] ?? null)
                            : 'Processing failed. Please try again or contact support.',
                        'createdAt'    => $notification->created_at->format('M j, Y \a\t g:i A'),
                        'age'          => $notification->created_at->diffForHumans(),
                    ]);
                @endphp
                @include('pages.notifications._row', compact('notification', 'data', 'isOk', 'payload'))
            @endforeach
        </div>

        <div class="mt-4">
            {{ $readPaginator->links() }}
        </div>
    </div>
    @endif

    {{-- ── Notification detail modal ──────────────────────────────── --}}
    <div
        x-show="modal"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[500] flex items-center justify-center p-4"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal()"></div>

        {{-- Panel --}}
        <div
            x-show="modal"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
            @click.stop
        >
            {{-- Close button --}}
            <button
                @click="closeModal()"
                class="absolute right-4 top-4 flex size-7 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>

            <template x-if="active">
                <div>
                    {{-- Header --}}
                    <div class="flex items-start gap-4 border-b border-zinc-100 px-6 py-5 dark:border-zinc-800">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                             :class="active.isOk ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                            <template x-if="active.isOk">
                                <svg class="size-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </template>
                            <template x-if="!active.isOk">
                                <svg class="size-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                </svg>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1 pr-8">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                               x-text="active.isOk ? 'File ready to download' : 'Processing failed'"></p>
                            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400"
                               x-text="active.originalName"></p>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">File</p>
                            <p class="break-all text-sm font-medium text-zinc-800 dark:text-zinc-200" x-text="active.originalName"></p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Status</p>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                  :class="active.isOk
                                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                      : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                                <span class="size-1.5 rounded-full" :class="active.isOk ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                <span x-text="active.isOk ? 'Completed' : 'Failed'"></span>
                            </span>
                        </div>
                        <template x-if="!active.isOk && active.errorMessage">
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Error</p>
                                <div class="overflow-hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 dark:border-red-900/40 dark:bg-red-950/20">
                                    <p class="break-words break-words text-xs leading-relaxed text-red-700 dark:text-red-400" x-text="active.errorMessage"></p>
                                </div>
                            </div>
                        </template>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Received</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400" x-text="active.createdAt"></p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                        <template x-if="pendingModalDelete">
                            <div class="mb-3 flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 dark:border-red-900/40 dark:bg-red-950/20">
                                <p class="text-xs font-medium text-red-700 dark:text-red-400">Delete this notification?</p>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="pendingModalDelete = false"
                                        class="rounded-lg border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                    >Cancel</button>
                                    <button
                                        @click="deleteActive()"
                                        class="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                                    >Yes, delete</button>
                                </div>
                            </div>
                        </template>
                        <div class="flex items-center justify-between">
                            <button
                                @click="pendingModalDelete = true"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                                Delete
                            </button>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="closeModal()"
                                    class="rounded-lg border border-zinc-200 bg-white px-4 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                >Close</button>
                                <template x-if="active.isOk && active.downloadUrl">
                                    <a :href="active.downloadUrl" target="_blank" @click="closeModal()"
                                       class="flex items-center gap-1.5 rounded-lg bg-cutcontour px-4 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                        </svg>
                                        Download
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

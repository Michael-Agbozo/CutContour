<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users — Admin')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    #[Computed]
    public function users(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return User::query()
            ->withCount('cutJobs')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);
    }

    public function toggleAdmin(int $userId): void
    {
        $user = User::find($userId);

        if (! $user || $user->id === auth()->id()) {
            return;
        }

        $user->update(['is_admin' => ! $user->is_admin]);
    }
};

?>

<div class="flex flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Users</h1>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Manage user accounts and admin access.</p>
        </div>
        <flux:button variant="ghost" size="sm" :href="route('admin.dashboard')" wire:navigate icon="arrow-left">
            Back to Dashboard
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="w-full sm:w-64">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or email…" icon="magnifying-glass" />
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">User</th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Email</th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Role</th>
                    <th wire:click="sort('created_at')" class="cursor-pointer px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                        Joined
                        @if($sortBy === 'created_at')
                            <span class="ml-1">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Jobs</th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">2FA</th>
                    <th class="px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                @forelse($this->users as $user)
                    <tr class="group hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                                <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $user->email }}
                        </td>
                        <td class="px-4 py-3">
                            @if($user->is_admin)
                                <span class="inline-flex rounded-full bg-cutcontour/10 px-2 py-0.5 text-[10px] font-semibold text-cutcontour">Admin</span>
                            @else
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">User</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-400">
                            {{ $user->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">
                            {{ $user->cut_jobs_count }}
                        </td>
                        <td class="px-4 py-3">
                            @if($user->two_factor_confirmed_at)
                                <flux:icon name="shield-check" class="size-4 text-emerald-500" />
                            @else
                                <flux:icon name="shield-exclamation" class="size-4 text-zinc-300 dark:text-zinc-600" />
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($user->id !== auth()->id())
                                <flux:button
                                    wire:click="toggleAdmin({{ $user->id }})"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $user->is_admin ? 'Remove Admin' : 'Make Admin' }}
                                </flux:button>
                            @else
                                <span class="text-[10px] text-zinc-400">You</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>
        {{ $this->users->links() }}
    </div>

</div>

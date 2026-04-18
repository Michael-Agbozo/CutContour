<?php

namespace App\Policies;

use App\Models\CutJob;
use App\Models\User;

class CutJobPolicy
{
    /**
     * Admins bypass all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CutJob $cutJob): bool
    {
        return $user->id === $cutJob->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only allow download when the user owns the job, processing finished
     * successfully, and an output file actually exists on disk.
     */
    public function download(User $user, CutJob $cutJob): bool
    {
        return $user->id === $cutJob->user_id
            && $cutJob->status === 'completed'
            && $cutJob->output_path !== null;
    }

    public function delete(User $user, CutJob $cutJob): bool
    {
        return $user->id === $cutJob->user_id;
    }
}

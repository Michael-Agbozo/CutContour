<?php

namespace App\Policies;

use App\Models\CutJob;
use App\Models\User;

class CutJobPolicy
{
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

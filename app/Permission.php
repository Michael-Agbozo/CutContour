<?php

namespace App;

use App\Models\User;

/**
 * Centralised permission definitions.
 *
 * Each case represents a named ability that can be checked via
 * Gate::allows() / @can / $user->can().
 *
 * Gates are registered automatically in AppServiceProvider.
 */
enum Permission: string
{
    /**
     * Access the admin area (/admin/*).
     */
    case AccessAdmin = 'access-admin';

    /**
     * Access the regular workspace (dashboard, jobs, notifications).
     */
    case AccessWorkspace = 'access-workspace';

    /**
     * Manage users (toggle admin, view user list).
     */
    case ManageUsers = 'manage-users';

    /**
     * Run system operations (cleanup, flush queue).
     */
    case ManageSystem = 'manage-system';

    /**
     * View all jobs across users.
     */
    case ViewAllJobs = 'view-all-jobs';

    /**
     * Resolve whether the given user holds this permission.
     */
    public function check(User $user): bool
    {
        $isAdmin = (bool) $user->is_admin;

        return match ($this) {
            self::AccessAdmin,
            self::ManageUsers,
            self::ManageSystem,
            self::ViewAllJobs => $isAdmin,

            self::AccessWorkspace => ! $isAdmin,
        };
    }
}

<?php

namespace App\Policies;

use App\Models\AppCrashLog;
use App\Models\User;

class AppCrashLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return $user instanceof User;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AppCrashLog $appCrashLog): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user) || $user->id === $appCrashLog->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }
}

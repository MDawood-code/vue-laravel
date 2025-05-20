<?php

namespace App\Policies;

use App\Models\ActivityType;
use App\Models\User;

class ActivityTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->type, [USER_TYPE_ADMIN, USER_TYPE_ADMIN_STAFF, USER_TYPE_SUPER_ADMIN]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ActivityType $activityType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->type, [USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ActivityType $activityType): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ActivityType $activityType): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ActivityType $activityType): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ActivityType $activityType): bool
    {
        return $this->create($user);
    }
}

<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function adminViewAny(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Device $device): bool
    {
        return $user->company_id === $device->company_id;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function adminView(User $user, Device $device): bool
    {
        return $this->adminViewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->adminViewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Device $device): bool
    {
        return $this->adminViewAny($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Device $device): bool
    {
        return $this->adminViewAny($user);
    }
}

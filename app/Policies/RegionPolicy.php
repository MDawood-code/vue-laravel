<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Auth::check();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Region $region): bool
    {
        return Auth::check();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return user_is_admin_or_super_admin($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Region $region): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Region $region): bool
    {
        return $this->create($user);
    }
}

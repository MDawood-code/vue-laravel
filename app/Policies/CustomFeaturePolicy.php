<?php

namespace App\Policies;

use App\Models\CustomFeature;
use App\Models\User;

class CustomFeaturePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomFeature $customFeature): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomFeature $customFeature): bool
    {
        return user_is_super_admin($user);
    }
}

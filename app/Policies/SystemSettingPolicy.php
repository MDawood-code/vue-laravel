<?php

namespace App\Policies;

use App\Models\User;

class SystemSettingPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool
    {
        return user_is_super_admin($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        return $this->view($user);
    }
}

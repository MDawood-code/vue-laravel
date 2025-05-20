<?php

namespace App\Policies;

use App\Models\Addon;
use App\Models\User;

class AddonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Addon $addon): bool
    {
        return $user->isSuperAdmin();
    }
}

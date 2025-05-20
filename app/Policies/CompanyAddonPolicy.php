<?php

namespace App\Policies;

use App\Models\Addon;
use App\Models\User;

class CompanyAddonPolicy
{
    /**
     * Determine whether the user can subscribe the model.
     */
    public function subscribe(User $user, Addon $addon): bool
    {
        return user_is_company_owner($user);
    }

    /**
     * Determine whether the user can unsubscribe the model.
     */
    public function unsubscribe(User $user, Addon $addon): bool
    {
        return $this->subscribe($user, $addon);
    }
}

<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CrmLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->id == $company->admin_staff_id || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
    }
}

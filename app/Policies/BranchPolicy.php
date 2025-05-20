<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Branch $branch): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || (user_is_company_owner($user) && $company->id === $user->company_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Branch $branch): bool
    {
        return user_is_admin_or_super_admin($user) || (user_is_company_owner($user) && $branch->company_id === $user->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return user_is_super_admin($user) || (user_is_company_owner($user) && $branch->company_id === $user->company_id);
    }
}

<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Company;
use App\Models\User;

class ActivityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->id == $company->admin_staff_id || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Activity $activity, int $companyId): bool
    {
        return $companyId == $activity->company_id && ($user->id == $activity->created_by || $user->id == $activity->assigned_to || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Activity $activity, int $companyId): bool
    {
        return $this->view($user, $activity, $companyId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Activity $activity, int $companyId): bool
    {
        return $companyId == $activity->company_id && ($user->id == $activity->created_by || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Activity $activity, int $companyId): bool
    {
        return $this->delete($user, $activity, $companyId);
    }
}

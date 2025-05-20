<?php

namespace App\Policies;

use App\Models\IssueType;
use App\Models\User;

class IssueTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IssueType $issueType): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return user_is_super_admin($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IssueType $issueType): bool
    {
        return $this->create($user);
    }
}

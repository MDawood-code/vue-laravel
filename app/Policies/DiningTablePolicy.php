<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return hasActiveTableManagementAddon($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DiningTable $diningTable): bool
    {
        if (user_is_company_owner()) {
            return $this->viewAny($user) && $diningTable->whereHas('branch', function ($query) use ($user): void {
                $query->where('company_id', $user->company_id);
            })->exists();
        } elseif (user_is_employee()) {
            return $this->viewAny($user) && $user->branch_id === $diningTable->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return user_is_company_owner() && $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DiningTable $diningTable): bool
    {
        return user_is_company_owner() && $this->viewAny($user) && $diningTable->whereHas('branch', function ($query) use ($user): void {
            $query->where('company_id', $user->company_id);
        })->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DiningTable $diningTable): bool
    {
        return user_is_company_owner() && $this->viewAny($user) && $diningTable->whereHas('branch', function ($query) use ($user): void {
            $query->where('company_id', $user->company_id);
        })->exists();
    }
}

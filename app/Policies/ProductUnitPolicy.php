<?php

namespace App\Policies;

use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ProductUnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        throw_if(! user_is_customer($user) || ! canAccessFeatures($user->company), new AuthorizationException('You are not an active user.'));

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductUnit $productUnit): bool
    {
        return $this->viewAny($user) && $user->company_id === $productUnit->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        throw_unless($user->can_add_edit_product, new AuthorizationException('You are not authorized to add unit.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductUnit $productUnit): bool
    {
        throw_unless($user->can_add_edit_product, new AuthorizationException('You are not authorized to update unit.'));

        return $this->view($user, $productUnit);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductUnit $productUnit): bool
    {
        return $this->view($user, $productUnit) && user_is_company_owner($user);
    }
}

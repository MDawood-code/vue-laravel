<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ProductPolicy
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
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        throw_if(! $user->can_add_edit_product && ! user_is_company_owner($user), new AuthorizationException('You are not authorized to add product.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update models.
     */
    public function update(User $user): bool
    {
        throw_if(! $user->can_add_edit_product && ! user_is_company_owner($user), new AuthorizationException('You are not authorized to update product.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete models.
     */
    public function delete(User $user): bool
    {
        throw_if(! $user->can_add_edit_product && ! user_is_company_owner($user), new AuthorizationException('You are not authorized to delete product.'));

        return $this->viewAny($user);
    }
}

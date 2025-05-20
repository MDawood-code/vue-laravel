<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Discount $discount): bool
    {
        return $user->company_id === $discount->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->type === USER_TYPE_BUSINESS_OWNER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Discount $discount): bool
    {
        return $user->type === USER_TYPE_BUSINESS_OWNER && $user->company_id === $discount->company_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Discount $discount): bool
    {
        return $user->company_id === $discount->company_id;
    }
}

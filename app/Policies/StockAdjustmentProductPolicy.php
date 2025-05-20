<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class StockAdjustmentProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        throw_if(! user_is_customer($user) || ! canAccessFeatures($user->company), new AuthorizationException('You are not an active user.'));

        throw_unless(hasActiveStockAddon($user), new AuthorizationException('You are not authorized for Inventory. Please Subscribe.'));

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user) && $user->company_id === $product->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        throw_unless($user->can_request_stock_adjustment, new AuthorizationException('You are not authorized to add adjustment.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        throw_unless($user->can_add_edit_product, new AuthorizationException('You are not authorized to update product.'));

        return $this->view($user, $product);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->view($user, $product) && user_is_company_owner($user);
    }
}

<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class StockPolicy
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
    public function view(User $user, int $productId): bool
    {
        throw_if($user->company_id !== Product::find($productId)?->company_id, new AuthorizationException('Stock not found.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can see branch stock.
     */
    public function branchStock(User $user, int $branchId): bool
    {
        throw_if($user->company->branches()->where('id', $branchId)->doesntExist(), new AuthorizationException('Invalid branch id.'));

        return $this->viewAny($user);
    }
}

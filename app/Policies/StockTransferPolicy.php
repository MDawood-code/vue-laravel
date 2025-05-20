<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class StockTransferPolicy
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
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        throw_unless(user_is_company_owner($user), new AuthorizationException('Only owner can directly make a transfer. You may request a transfer.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can request transfer.
     */
    public function request(User $user): bool
    {
        throw_if(! $user->can_request_stock_transfer && ! user_is_company_owner($user), new AuthorizationException('You are not authorized to request stock transfer.'));

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can approve a transfer request.
     */
    public function approve(User $user, StockTransfer $stockTransfer): bool
    {
        if (user_is_company_owner($user)) {
            throw_unless($user->company->branches->pluck('id')->contains($stockTransfer->branch_from_id), new AuthorizationException('You are not authorized to approve stock transfer request for this branch.'));
        } elseif ($user->branch_id != $stockTransfer->branch_from_id || ! $user->can_approve_stock_transfer) {
            throw new AuthorizationException('You are not authorized to approve stock transfer request for this branch.');
        }

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can cancel a transfer request.
     */
    public function cancel(User $user, StockTransfer $stockTransfer): bool
    {
        if (user_is_company_owner($user)) {
            throw_unless($user->company->branches->pluck('id')->contains($stockTransfer->branch_from_id), new AuthorizationException('You are not authorized to cancel stock transfer request for this branch.'));
        } elseif ($user->branch_id != $stockTransfer->branch_to_id || ! $user->can_request_stock_transfer) {
            throw new AuthorizationException('You are not authorized to cancel stock transfer request for this branch.');
        }

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can reject a transfer request.
     */
    public function reject(User $user, StockTransfer $stockTransfer): bool
    {
        if (user_is_company_owner($user)) {
            throw_unless($user->company->branches->pluck('id')->contains($stockTransfer->branch_from_id), new AuthorizationException('You are not authorized to reject stock transfer request for this branch.'));
        } elseif ($user->branch_id != $stockTransfer->branch_from_id || ! $user->can_approve_stock_transfer) {
            throw new AuthorizationException('You are not authorized to reject stock transfer request for this branch.');
        }

        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        if (user_is_company_owner($user)) {
            throw_unless($user->company->branches->pluck('id')->contains($stockTransfer->branch_from_id), new AuthorizationException('You are not authorized to update stock transfer request for this branch.'));
        } elseif (($user->branch_id != $stockTransfer->branch_from_id || ! $user->can_approve_stock_transfer) && ($user->branch_id != $stockTransfer->branch_to_id || ! $user->can_request_stock_transfer)) {
            throw new AuthorizationException('You are not authorized to update stock transfer request for this branch.');
        }

        return $this->viewAny($user);
    }
}

<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TransactionPolicy
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
    public function view(User $user, Transaction $transaction): bool
    {
        throw_if(! user_is_customer($user) || ! canAccessFeatures($user->company), new AuthorizationException('You are not an active user.'));

        return $user->company_id === $transaction->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->company->status === COMPANY_STATUS_ACTIVE;
    }

    /**
     * Determine whether the user can create sales invoice.
     */
    public function createSaleInvoice(User $user): bool
    {
        return $this->create($user) && hasActiveA4SalesInvoiceAddon($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        throw_if(! user_is_customer($user) || ! canAccessFeatures($user->company), new AuthorizationException('You are not an active user.'));

        return $transaction->company_id === $user->company_id && (
            user_is_company_owner() || $transaction->user_id == $user->id || $transaction->waiter_id == $user->id
            || (user_is_employee($user) && ! $user->is_waiter && $transaction->branch_id === $user->branch_id)
        ) && $user->company->status === COMPANY_STATUS_ACTIVE;
    }

    // /**
    //  * Determine whether the user can delete the model.
    //  */
    // public function delete(User $user, Transaction $transaction): bool
    // {
    //     //
    // }

    /**
     * Determine whether the user can refund the model.
     */
    public function refund(User $user, Transaction $transaction): bool
    {
        throw_if(! user_is_customer($user) || ! canAccessFeatures($user->company), new AuthorizationException('You are not an active user.'));

        return $user->can_refund_transaction
        && $transaction->company_id === $user->company_id
        && (($user->type === USER_TYPE_EMPLOYEE
            && $transaction->branch_id === $user->branch_id)
            || $user->type === USER_TYPE_BUSINESS_OWNER)
        && $transaction->is_refunded === BOOLEAN_FALSE
        && $transaction->status === TransactionStatus::Completed
        && $user->company->status === COMPANY_STATUS_ACTIVE;
    }

    public function salesSummary(User $user): bool
    {
        return user_is_customer($user) && ! $user->is_waiter;
    }

    public function salesByItems(User $user): bool
    {
        return $this->salesSummary($user);
    }

    public function salesByCategories(User $user): bool
    {
        return $this->salesSummary($user);
    }

    public function refundsByItems(User $user): bool
    {
        return $this->salesSummary($user);
    }

    public function refundsByCategories(User $user): bool
    {
        return $this->salesSummary($user);
    }

    public function salesByBranches(User $user): bool
    {
        return $this->salesSummary($user);
    }

    public function homeDataSummary(User $user): bool
    {
        return user_is_customer($user);
    }
}

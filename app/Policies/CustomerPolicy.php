<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->can_view_customer && hasActiveCustomerManagementAddon($user)) {
            return true;
        }
        throw new AuthorizationException('You are not allowed to view customers.');
       
    }
    

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->can_view_customer && $user->company_id === $customer->company_id && hasActiveCustomerManagementAddon($user)) {
            return true;
        }
        throw new AuthorizationException('You are not allowed to view this customer.');
    }
    
    

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->can_add_edit_customer && hasActiveCustomerManagementAddon($user)) {
            return $this->viewAny($user);
        }
        throw new AuthorizationException('You are not allowed to add this customer.');
       
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user,Customer $customer): bool
    {
        if ($user->can_add_edit_customer && $user->company_id === $customer->company_id && hasActiveCustomerManagementAddon($user)) {
            return $this->viewAny($user);
        }
        throw new AuthorizationException('You are not allowed to update this customer.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user ,Customer $customer): bool
    {
        if ($user->can_add_edit_customer && $user->company_id === $customer->company_id && hasActiveCustomerManagementAddon($user)) {
            return $this->viewAny($user);
        }
        throw new AuthorizationException('You are not allowed to delete this customer.');
    }

  
}

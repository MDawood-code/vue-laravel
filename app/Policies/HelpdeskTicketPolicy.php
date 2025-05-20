<?php

namespace App\Policies;

use App\Models\HelpdeskTicket;
use App\Models\User;

class HelpdeskTicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function adminViewAny(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HelpdeskTicket $helpdeskTicket): bool
    {
        return $user->id === $helpdeskTicket->created_by;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function adminView(User $user, HelpdeskTicket $helpdeskTicket): bool
    {
        return $this->adminViewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HelpdeskTicket $helpdeskTicket): bool
    {
        return $user->id === $helpdeskTicket->created_by;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function adminUpdate(User $user, HelpdeskTicket $helpdeskTicket): bool
    {
        return user_is_admin_or_super_admin($user) || (user_is_staff($user) && $user->id === $helpdeskTicket->assigned_to);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HelpdeskTicket $helpdeskTicket): bool
    {
        return user_is_admin_or_super_admin($user) || $user->id === $helpdeskTicket->created_by;
    }
}

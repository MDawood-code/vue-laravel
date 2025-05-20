<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Company;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Company $company): bool
    {
        return $user->id == $company->admin_staff_id || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Comment $comment): bool
    {
        return $user->id == $comment->created_by || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $this->view($user, $comment);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id == $comment->created_by || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $this->delete($user, $comment);
    }
}

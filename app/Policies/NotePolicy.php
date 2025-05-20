<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Note;
use App\Models\User;

class NotePolicy
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
    public function view(User $user, Note $note, int $companyId): bool
    {
        return $note->company_id === $companyId && ($user->id == $note->created_by || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Note $note, int $companyId): bool
    {
        return $this->view($user, $note, $companyId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Note $note, int $companyId): bool
    {
        return $note->company_id === $companyId && ($user->id == $note->created_by || in_array($user->type, [USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN]));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Note $note, int $companyId): bool
    {
        return $this->delete($user, $note, $companyId);
    }
}

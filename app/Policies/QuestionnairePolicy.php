<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class QuestionnairePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || $user->isAgentForCompany($company);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }
}

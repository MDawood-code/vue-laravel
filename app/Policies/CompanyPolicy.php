<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine whether the user can view all companies.
     */
    public function viewAny(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function adminView(User $user, Company $company): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function customerView(User $user, Company $company): bool
    {
        return user_is_customer($user) && $user->company_id === $company->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateByAdmin(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can delete the uploaded file.
     */
    public function deleteUploadedFile(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user);
    }

    /**
     * Determine whether the user can activate the company.
     */
    public function activate(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can deactivate the company.
     */
    public function deactivate(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user);
    }

    /**
     * Determine whether the user can changeAdminStaff the company.
     */
    public function changeAdminStaff(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user);
    }

    /**
     * Determine whether the user can send failed odoo resources.
     */
    public function sendFailedOdooResources(User $user): bool
    {
        return user_is_admin_or_super_admin($user);
    }
}

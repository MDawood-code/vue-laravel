<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function onlySuperAdmin(User $user): bool
    {
        return user_is_super_admin($user);
    }

    public function manageAdmin(User $user, User $admin): bool
    {
        return $this->onlySuperAdmin($user) && user_is_admin($admin);
    }

    public function onlyAdminAndSuperAdmin(User $user): bool
    {
        return user_is_admin_or_super_admin($user);
    }

    public function manageStaff(User $user, User $staff): bool
    {
        return $this->onlyAdminAndSuperAdmin($user) && user_is_staff($staff);
    }

    public function adminDashboard(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    public function crew(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    public function updateUserCompany(User $user): bool
    {
        return user_is_customer($user);
    }

    public function viewAnyEmployees(User $user): bool
    {
        return user_is_customer($user);
    }

    public function createEmployee(User $user): bool
    {
        return user_is_company_owner($user);
    }

    public function updateEmployee(User $user, User $employee): bool
    {
        return $this->createEmployee($user) && user_is_employee($employee) && $user->company_id === $employee->company_id;
    }
    public function updateEmployeeOurself(User $user, User $employee): bool
    {
        return $this->createEmployee($user) && $user->company_id === $employee->company_id;
    }
    public function sendEmployeeToOddo(User $user, User $employee): bool
    {
        return $user->company_id === $employee->company_id;
    }

    public function referralDashboard(User $user): bool
    {
        return user_is_referral($user);
    }

    public function resellerDashboard(User $user): bool
    {
        return user_is_reseller($user);
    }
}

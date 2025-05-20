<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function adminViewAny(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can activate annual trial subscription of the company.
     */
    public function activateAnnualTrialSubscription(User $user, Company $company): bool
    {
        return user_is_admin_or_super_admin($user) || $user->isAgentForCompany($company);
    }

    /**
     * Determine whether the user can extend trial subscription of the company.
     */
    public function extendTrialSubscription(User $user, Company $company): bool
    {
        return $this->activateAnnualTrialSubscription($user, $company);
    }

    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    public function create(User $user): bool
    {
        return user_is_company_owner($user);
    }

    public function renew(User $user): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $this->create($user) && $user->company_id === $subscription->company_id;
    }

    public function userLicensePricing(User $user, Subscription $subscription): bool
    {
        return $this->viewAny($user) && $user->company_id === $subscription->company_id;
    }
}

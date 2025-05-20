<?php

namespace App\Policies;

use App\Models\CustomFeature;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ExternalIntegrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return user_is_customer($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! user_is_customer($user)) {
            return false;
        }

        throw_if(CustomFeature::where('title', 'External Integration')->where('status', false)->exists(), new AuthorizationException('External Integration not available.'));

        return true;
    }

    /**
     * Determine whether the user can test.
     */
    public function test(User $user): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete.
     */
    public function delete(User $user, ExternalIntegration $externalIntegration): bool
    {
        return $this->create($user) && $user->company_id === $externalIntegration->company_id;
    }
}

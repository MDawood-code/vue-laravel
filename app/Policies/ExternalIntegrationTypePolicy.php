<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ExternalIntegrationTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Auth::check();
    }
}

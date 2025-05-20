<?php

namespace App\Events;

use App\Models\Company;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyUserForOdooCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Company $company;

    /**
     * @var array<int, User>
     */
    public $users;

    /**
     * Create a new event instance.
     */
    public function __construct(public User $user)
    {
        $this->company = $user->company;
        $this->users = [$user];
    }
}

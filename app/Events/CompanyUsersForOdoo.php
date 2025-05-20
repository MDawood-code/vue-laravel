<?php

namespace App\Events;

use App\Models\Company;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyUsersForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, User>
     */
    public $users;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->users = $company->users()->where('odoo_reference_id', null)->get();
    }
}

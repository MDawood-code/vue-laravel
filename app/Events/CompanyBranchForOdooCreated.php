<?php

namespace App\Events;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyBranchForOdooCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Company $company;

    /**
     * @var array<int, Branch>
     */
    public $branches;

    /**
     * Create a new event instance.
     */
    public function __construct(public Branch $branch)
    {
        $this->company = $branch->company;
        $this->branches = [$branch];
    }
}

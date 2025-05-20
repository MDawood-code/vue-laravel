<?php

namespace App\Events;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyBranchesForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, Branch>
     */
    public $branches;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->branches = $company->branches()->where('odoo_reference_id', null)->get();
    }
}

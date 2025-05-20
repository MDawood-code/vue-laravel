<?php

namespace App\Events;

use App\Models\Company;
use App\Models\ProductUnit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUnitsForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, ProductUnit>
     */
    public $units;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->units = $company->productUnits()->where('odoo_reference_id', null)->get();
    }
}

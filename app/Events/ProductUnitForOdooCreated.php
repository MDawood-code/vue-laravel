<?php

namespace App\Events;

use App\Models\Company;
use App\Models\ProductUnit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUnitForOdooCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Company $company;

    /**
     * @var array<int, ProductUnit>
     */
    public $units;

    /**
     * Create a new event instance.
     */
    public function __construct(public ProductUnit $productUnit)
    {
        $this->company = $productUnit->company;
        $this->units = [$productUnit];
    }
}

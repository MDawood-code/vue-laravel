<?php

namespace App\Events;

use App\Models\Company;
use App\Models\ProductTaxForOdoo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductTaxesForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, ProductTaxForOdoo>
     */
    public $taxes;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->taxes = ProductTaxForOdoo::all();
    }
}

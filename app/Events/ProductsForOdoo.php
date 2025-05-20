<?php

namespace App\Events;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductsForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, Product>
     */
    public $products;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->products = $company->products()->where('odoo_reference_id', null)->get();
    }
}

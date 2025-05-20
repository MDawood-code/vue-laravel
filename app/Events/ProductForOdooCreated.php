<?php

namespace App\Events;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductForOdooCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Company $company;

    /**
     * @var array<int, Product>
     */
    public $products;

    /**
     * Create a new event instance.
     */
    public function __construct(Product $product)
    {
        $this->company = $product->company;
        $this->products = [$product];
    }
}

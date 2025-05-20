<?php

namespace App\Events;

use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductCategoryForOdooCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Company $company;

    /**
     * @var array<int, ProductCategory>
     */
    public $categories;

    /**
     * Create a new event instance.
     */
    public function __construct(ProductCategory $productCategory)
    {
        $this->company = $productCategory->company;
        $this->categories = [$productCategory];
    }
}

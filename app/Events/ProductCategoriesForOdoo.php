<?php

namespace App\Events;

use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductCategoriesForOdoo
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Collection<int, ProductCategory>
     */
    public $categories;

    /**
     * Create a new event instance.
     */
    public function __construct(public Company $company)
    {
        $this->categories = $company->productCategories()->where('odoo_reference_id', null)->get();
    }
}

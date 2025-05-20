<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\ProductCategoriesForOdoo;
use App\Events\ProductCategoryForOdooCreated;
use App\Http\Resources\ProductCategoryOdooResource;
use App\Models\ProductCategory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProductCategoriesToOdoo implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProductCategoriesForOdoo|ProductCategoryForOdooCreated $event): void
    {
        $categories = ProductCategoryOdooResource::collection($event->categories);

        try {
            Log::debug('Categories Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/categories/create', [
                'token' => $odooIntegration->secret_key,
                'categories' => $categories,
            ]);
            Log::debug('Odoo Categories API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                foreach ($odoo_response->result->reference_ids as $reference) {
                    ProductCategory::where('id', $reference->anypos_id)->update(['odoo_reference_id' => $reference->odoo_reference_id]);
                }
            }
        } catch (Throwable) {
            Log::debug('Odoo Categories API FAILED');
        }
    }
}

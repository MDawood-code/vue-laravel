<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\ProductForOdooCreated;
use App\Events\ProductsForOdoo;
use App\Http\Resources\ProductOdooResource;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProductsToOdoo implements ShouldQueue
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
    public function handle(ProductsForOdoo|ProductForOdooCreated $event): void
    {
        $products = ProductOdooResource::collection($event->products);

        try {
            Log::debug('Products Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/products/create', [
                'token' => $odooIntegration->secret_key,
                'products' => $products,
            ]);
            Log::debug('Odoo Products API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                foreach ($odoo_response->result->reference_ids as $reference) {
                    Product::where('id', $reference->anypos_id)->update(['odoo_reference_id' => $reference->odoo_reference_id]);
                }
            }
        } catch (Throwable) {
            Log::debug('Odoo Products API FAILED');
        }
    }
}

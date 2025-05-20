<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\ProductUnitForOdooCreated;
use App\Events\ProductUnitsForOdoo;
use App\Http\Resources\ProductUnitOdooResource;
use App\Models\ProductUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProductUnitsToOdoo implements ShouldQueue
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
    public function handle(ProductUnitsForOdoo|ProductUnitForOdooCreated $event): void
    {
        $units = ProductUnitOdooResource::collection($event->units);

        try {
            Log::debug('Units Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/units/create', [
                'token' => $odooIntegration->secret_key,
                'units' => $units,
            ]);
            Log::debug('Odoo Units API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                foreach ($odoo_response->result->reference_ids as $reference) {
                    ProductUnit::where('id', $reference->anypos_id)->update(['odoo_reference_id' => $reference->odoo_reference_id]);
                }
            }
        } catch (Throwable) {
            Log::debug('Odoo Units API FAILED');
        }
    }
}

<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\ProductTaxesForOdoo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProductTaxesToOdoo implements ShouldQueue
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
    public function handle(ProductTaxesForOdoo $event): void
    {
        $taxes = $event->taxes;

        try {
            Log::debug('Taxes Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/taxes/create', [
                'token' => $odooIntegration->secret_key,
                'taxes' => $taxes,
            ]);
            Log::debug('Odoo Taxes API');
            Log::debug($response->body());
        } catch (Throwable) {
            Log::debug('Odoo Taxes API FAILED');
        }
    }
}

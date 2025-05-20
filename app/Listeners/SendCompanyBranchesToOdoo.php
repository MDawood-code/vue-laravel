<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\CompanyBranchesForOdoo;
use App\Events\CompanyBranchForOdooCreated;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCompanyBranchesToOdoo implements ShouldQueue
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
    public function handle(CompanyBranchesForOdoo|CompanyBranchForOdooCreated $event): void
    {
        $branches = BranchResource::collection($event->branches);

        try {
            Log::debug('Branches Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/branches/create', [
                'token' => $odooIntegration->secret_key,
                'branches' => $branches,
            ]);
            Log::debug('Odoo Branches API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                foreach ($odoo_response->result->reference_ids as $reference) {
                    Branch::where('id', $reference->anypos_id)->update(['odoo_reference_id' => $reference->odoo_reference_id]);
                }
            }
        } catch (Throwable) {
            Log::debug('Odoo Branches API FAILED');
        }
    }
}

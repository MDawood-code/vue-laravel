<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\CompanyUserForOdooCreated;
use App\Events\CompanyUsersForOdoo;
use App\Http\Resources\CompanyUserResource;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCompanyUsersToOdoo implements ShouldQueue
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
    public function handle(CompanyUsersForOdoo|CompanyUserForOdooCreated $event): void
    {
        $users = CompanyUserResource::collection($event->users);

        try {
            Log::debug('Users Data Sent:');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->company->odooIntegration();
            $response = Http::post($odooIntegration->url.'/users/create', [
                'token' => $odooIntegration->secret_key,
                'users' => $users,
            ]);
            Log::debug('Odoo Users API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                foreach ($odoo_response->result->reference_ids as $reference) {
                    User::where('id', $reference->anypos_id)->update(['odoo_reference_id' => $reference->odoo_reference_id]);
                }
            }
        } catch (Throwable) {
            Log::debug('Odoo Users API FAILED');
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\CompanyActivated;
use App\Mail\OdooCompanyCreationFailed;
use App\Models\CrmLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCompanyToOdoo implements ShouldQueue
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
    public function handle(CompanyActivated $event): void
    {
        // Call api for odoo that user has been registered
        Log::debug([
            'id' => $event->company->id,
            'name' => $event->company->name,
            'email' => $event->company->owner?->email,
            'phone' => $event->company->owner?->phone,
            'address' => $event->company->billing_address,
            'city_en' => $event->company->city?->name_en,
            'city_ar' => $event->company->city?->name_ar,
            'region_en' => $event->company->billingState?->name_en,
            'region_ar' => $event->company->billingState?->name_ar,
            'country_en' => 'Kingdom of Saudi Arabia',
            'country_ar' => 'المملكة العربية السعودية',
            'vat' => $event->company->vat,
        ]);
        try {
            $odoo_response = Http::post(config('odoo.base_url').'create_user', [
                'id' => $event->company->id,
                'name' => $event->company->name,
                'email' => $event->company->owner?->email,
                'phone' => $event->company->owner?->phone,
                'address' => $event->company->billing_address,
                'city_en' => $event->company->city?->name_en,
                'city_ar' => $event->company->city?->name_ar,
                'region_en' => $event->company->billingState?->name_en,
                'region_ar' => $event->company->billingState?->name_ar,
                'country_en' => 'Kingdom of Saudi Arabia',
                'country_ar' => 'المملكة العربية السعودية',
                'vat' => $event->company->vat,
            ]);
            Log::debug('Odoo Register API');
            Log::debug($odoo_response->body());
            $odoo_response = json_decode($odoo_response->body());

            if ($odoo_response->result && $odoo_response->result->status == true) {
                $previously_available_on_odoo = $event->company->created_on_odoo;
                $event->company->created_on_odoo = true;
                $event->company->save();

                if ($previously_available_on_odoo) {
                    CrmLog::create([
                        'company_id' => $event->company->id,
                        'created_by' => auth()->id(),
                        'action' => 'updated company on Odoo',
                    ]);
                } else {
                    CrmLog::create([
                        'company_id' => $event->company->id,
                        'action' => 'Company created on Odoo',
                    ]);
                }
            }
        } catch (Throwable $th) {
            Log::debug('Odoo Register API FAILED');
            Mail::to(env('REPORTING_EMAIL'))->send(new OdooCompanyCreationFailed($event->company, $th->getMessage()));

            CrmLog::create([
                'company_id' => $event->company->id,
                'action' => 'Company creation on Odoo Failed',
            ]);
        }
    }
}

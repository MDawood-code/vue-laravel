<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Company **/
class CompanyResource extends JsonResource
{
    public function __construct($resource, protected bool $add_branches = true, protected bool $add_employees = false, protected bool $add_subscriptions = true)
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
    
        $response_array = [
            'id' => $this->id,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'business_type_verification' => new BusinessTypeVerificationResource($this->businessTypeVerification),
            'cr' => $this->cr,
            'cr_certificate' => $this->cr_certificate ? asset($this->cr_certificate) : null,
            'vat' => $this->vat,
            'vat_certificate' => $this->vat_certificate ? asset($this->vat_certificate) : null,
            'is_vat_exempt' => (bool) $this->is_vat_exempt,
            'logo' => $this->logo ? asset($this->logo) : null,
            'code' => $this->code,
            'no_of_employees' => $this->employees->count(),
            'is_active' => (bool) $this->is_active,
            'active_addons' => CompactCompanyAddonResource::collection($this->activeAddons),
            // 'selected_addons' => CompactCompanyAddonResource::collection($this->selectedAddons),
            'round_off' => (bool) $this->round_off,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'last_active_at' => getHumanReadableDateInDays($this->last_active_at ? Carbon::parse($this->last_active_at) : null
        ),
            'active_subscription' => $this->active_subscription ? new SubscriptionResource($this->active_subscription) : null,
            'yearly_trial_availed' => $this->hasAvailedYearlyTrial(),
            'is_billing_add_same_as_postal' => (bool) $this->is_billing_add_same_as_postal,
            'billing_address' => $this->billing_address,
            'billing_city' => new CityResource($this->city),
            // 'address' => $this->branch->address,
            // 'billing_state' => $this->billing_state,
            'billing_state' => new RegionResource($this->billingState),
            'billing_country' => $this->billing_country,
            'billing_post_code' => $this->billing_post_code,
            'preferred_contact_time' => $this->owner->preferred_contact_time ?? null,
            'owner_name' => $this->owner->name ?? null,
            'owner_contact' => $this->owner->phone ?? null,
            'admin_staff' => $this->adminStaff ? [
                'id' => $this->admin_staff_id,
                'name' => $this->adminStaff->name,
            ] : null,
            'external_integrations' => ExternalIntegrationResource::collection($this->externalIntegrations),
            'is_onboarding_complete' => $this->is_onboarding_complete,
        ];

        // Add the reseller details if reseller_number is set
        if ($this->reseller_number) {
            $reseller = User::where('reseller_number', $this->reseller_number)->first();
            if ($reseller) {
                $response_array['reseller'] = [
                    'id' => $reseller->id,
                    'first_name' => $reseller->first_name,
                    'last_name' => $reseller->last_name,
                    'phone' => $reseller->phone,
                    'reseller_number' => $reseller->reseller_number,
                ];
            }
        }
        if ($this->add_employees) {
            $response_array['employees'] = CompanyUserResource::collection($this->employees);
        }

        if ($this->add_subscriptions) {
            $response_array['subscriptions'] = SubscriptionResource::collection($this->subscriptions);
        }

        if ($this->add_branches) {
            $response_array['branches'] = BranchResource::collection($this->branches);
        }
        if ($this->branches->isNotEmpty()) {
            $response_array['address'] = $this->branches->first()->address;
        }
        $response_array['devices'] = DeviceResource::collection($this->devices);
        $invoices = $this->invoices()->latest()->with(['payments' => function (Builder $query): void {
            $query->where('status', PAYMENT_STATUS_PAID);
        }])->get();
        $response_array['invoices'] = InvoiceResourceWithoutCompany::collection($invoices);

        if (user_is_admin_or_staff() || user_is_super_admin()) {
            $response_array['cr_verification'] = $this->cr_verification ? asset($this->cr_verification) : null;
            $response_array['vat_verification'] = $this->vat_verification ? asset($this->vat_verification) : null;
        }

        return $response_array;
    }
}

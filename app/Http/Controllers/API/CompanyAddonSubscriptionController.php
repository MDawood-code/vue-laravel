<?php

namespace App\Http\Controllers\API;

use App\Enums\AddonName;
use App\Events\InvoiceGenerated;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyAddonResource;
use App\Http\Traits\ApiResponseHelpers;
use App\Models\Addon;
use App\Models\Company;
use App\Models\CompanyAddon;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer
 *
 * @subgroup CompanyAddon
 *
 * @subgroupDescription APIs for managing CompanyAddon
 */
class CompanyAddonSubscriptionController extends Controller
{
    use ApiResponseHelpers;

    /**
     * Subscribe to the addon
     */
    public function subscribe(Request $request, Addon $addon): JsonResponse
    {
        $this->authorize('subscribe', [CompanyAddon::class, $addon]);

        $authUser = auth()->guard('api')->user();
        $company = $authUser->company->load('activeAddons');

        // if company has already active addon, abort
        if ($company->activeAddons->contains('addon_id', $addon->id)) {
            return $this->respondError('Company already has an active addon.');
        }

        // Check if any other addons are to be subscribed first
        $requiredAddons = $addon->dependentAddons()->get(['addons.id', 'name']);
        if ($requiredAddons) {
            $requiredAddons = $requiredAddons->keyBy('name');
            $errorMessages = [];
            foreach($requiredAddons as $requiredAddon) {
                if (! $company->activeAddons->pluck('addon_id')->contains($requiredAddon->id)) {
                    $errorMessages[] = $requiredAddon->name;
                }
            }
            if ($errorMessages !== []) {
                return $this->respondError("You must subscribe to " . implodeWithAnd($errorMessages) . " Addon(s) first.");
            }
        }

        // Between Waiter Addon and Job Management Addon, one can be subscribed at a time.
        $errorResponse = $this->checkIfOtherAddonIsActive($company, $addon, AddonName::JobManagement->value, AddonName::WaiterManagement->value);
        if ($errorResponse instanceof JsonResponse) {
            return $errorResponse;
        }

        $errorResponse = $this->checkIfOtherAddonIsActive($company, $addon, AddonName::WaiterManagement->value, AddonName::JobManagement->value);
        if ($errorResponse instanceof JsonResponse) {
            return $errorResponse;
        }

        // Get last such addon if user had purchased it previously. This is for trial info
        $prevCompanyAddon = $authUser->company->addons()->where('addon_id', $addon->id)->latest()->first();
        if ($prevCompanyAddon) {
            $trialValidityDays = $prevCompanyAddon->trial_validity_days;
            $trialStartedAt = $prevCompanyAddon->trial_started_at;
            $trialEndedAt = $prevCompanyAddon->trial_ended_at;
        } else {
            $trialValidityDays = $addon->trial_validity_days;
            $trialStartedAt = $addon->trial_validity_days === 0 ? null : Carbon::now()->toDateString();
            $trialEndedAt = $addon->trial_validity_days === 0 ? null : Carbon::now()->addDays($addon->trial_validity_days)->toDateString();
        }

        $companyAddon = new CompanyAddon;
        $companyAddon->addon_id = $addon->id;
        $companyAddon->company_id = $authUser->company_id;
        $companyAddon->price = $addon->price;
        $companyAddon->discount = $addon->discount;
        $companyAddon->trial_validity_days = $trialValidityDays;
        $companyAddon->trial_started_at = $trialStartedAt;
        $companyAddon->trial_ended_at = $trialEndedAt;

        $subscriptionScheme = getSystemSubscriptionScheme();
        if ($subscriptionScheme && $subscriptionScheme === 'daily') {
            $companyAddon->status = BOOLEAN_TRUE;
            $companyAddon->start_date = Carbon::now()->toDateString();
            $companyAddon->end_date = null;
        } else {
            $companyAddon->status = BOOLEAN_FALSE;
        }

        $companyAddon->save();

        // if stock addon, initialize stocks, give permissions to owner
        if ($addon->name === AddonName::Stock->value) {
            $company->createStocksForBranchesIfNotExist();
            $owner = $company->owner;
            $owner->can_request_stock_transfer = true;
            $owner->can_approve_stock_transfer = true;
            $owner->can_request_stock_adjustment = true;
            $owner->can_add_edit_customer = true;
            $owner->can_add_pay_sales_invoice = true;
            $owner->save();
        }

        // generate invoice in case subscription is monthly/yearly
        if ($subscriptionScheme && $subscriptionScheme !== 'daily') {
            $invoice = Invoice::generateAddonInvoice($companyAddon, $authUser->company);
            InvoiceGenerated::dispatch($invoice);
        }

        return $this->respondCreated([
            'success' => true,
            'message' => 'Addon subscribed successfully.',
            'data' => [
                'company_addon' => new CompanyAddonResource($companyAddon),
            ],
        ]);
    }

    /**
     * UnSubscribe from the addon
     */
    public function unSubscribe(Request $request, Addon $addon): JsonResponse
    {
        $this->authorize('unsubscribe', [CompanyAddon::class, $addon]);

        $authUser = auth()->guard('api')->user();

        // if company does not have active addon, abort
        /** @var ?CompanyAddon $companyAddon */
        $companyAddon = $authUser->company->activeAddons()->where('addon_id', $addon->id)->first();

        if (! $companyAddon) {
            return $this->respondError('Company is not subscribed to the addon.');
        }

        if ($companyAddon->addon?->billing_cycle !== ADDON_BILLING_DAILY) {
            return $this->respondError('Only daily customers can unsubscribe.');
        }

        // Recursively unsubscribe dependent addons
        $this->unsubscribeDependentAddons($addon, $authUser->company);

        $companyAddon->status = BOOLEAN_FALSE;
        $companyAddon->end_date = Carbon::now()->toDateString();

        $companyAddon->save();

        return $this->respondWithSuccess([
            'success' => true,
            'message' => 'Addon un-subscribed successfully.',
            'data' => [],
        ]);
    }

    private function checkIfOtherAddonIsActive(Company $company, Addon $addon, string $otherAddonName, string $errorAddonName): ?JsonResponse
    {
        $errorAddonId = Addon::where('name', $errorAddonName)->first()?->id;
        if ($addon->name === $otherAddonName && $company->activeAddons->pluck('addon_id')->contains($errorAddonId)) {
            return $this->respondError("You cannot subscribe to {$addon->name} as you already have an active {$errorAddonName}.");
        }

        return null; // Indicate success
    }

    private function unsubscribeDependentAddons(Addon $addon, Company $company): void
    {
        $requiredByAddons = $addon->requiredByAddons()->with(['activeCompanyAddons' => function ($query) use ($company): void {
            $query->where('company_id', $company->id)->where('status', true);
        }])->get();

        foreach ($requiredByAddons as $dependentAddon) {
            /** @var ?CompanyAddon $companyAddon */
            foreach ($dependentAddon->activeCompanyAddons as $companyAddon) {
                $companyAddon->status = BOOLEAN_FALSE;
                $companyAddon->end_date = Carbon::now()->toDateString();
                $companyAddon->save();
            }
            // Recursively unsubscribe addons dependent on this addon
            $this->unsubscribeDependentAddons($dependentAddon, $company);
        }
    }
}

<?php

namespace App\Http\Controllers\API\Admin;

use App\Events\InvoiceGenerated;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup Subscription
 *
 * @subgroupDescription APIs for managing Subscription
 */
class SubscriptionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('adminViewAny', Subscription::class);

        $sub_near_expiry = is_null($request->sub_near_expiry) ? null : $request->boolean('sub_near_expiry');
        $company = is_null($request->company) ? null : $request->string('company');

        $today = Carbon::now();
        $after_14 = Carbon::now()->addDays(14);

        $subscriptions = Subscription::latest()
            ->with('company')
            ->when($sub_near_expiry, function (Builder $query, ?bool $sub_near_expiry) use ($today, $after_14): void {
                $query->whereBetween('end_date', [$today, $after_14]);
            })
            ->when($company, function (Builder $query) use ($company): void {
                $query->whereHas('company', function (Builder $query) use ($company): void {
                    $query->where('name', 'like', '%'.$company.'%');
                });
            })
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions List Response',
            'data' => [
                'subscriptions' => SubscriptionResource::collection($subscriptions),
                'pagination' => [
                    'total' => $subscriptions->total(),
                    'current_page' => $subscriptions->currentPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total_pages' => ceil($subscriptions->total() / $subscriptions->perPage()),
                    'has_more_pages' => $subscriptions->hasMorePages(),
                    'next_page_url' => $subscriptions->nextPageUrl(),
                    'previous_page_url' => $subscriptions->previousPageUrl(),
                ],
            ],
        ]);
    }

    /**
     * Activate annual trial subscription of the specified company
     */
    public function activateAnnualTrialSubscription(Company $company): JsonResponse
    {
        $this->authorize('activateAnnualTrialSubscription', [Subscription::class, $company]);

        if ($company->devices->isNotEmpty()) {
            if (! $company->hasAvailedYearlyTrial()) {
                $lastSubscription = $company->subscriptions()->latest()->first();

                $plan_type = $lastSubscription->type;

                $subscriptionPlan = SubscriptionPlan::where('type', $plan_type)
                    ->where('period', PERIOD_YEARLY)
                    ->where('is_trial', BOOLEAN_TRUE)
                    ->where('validity_days', 365)
                    ->first();

                // Add Subscription
                $subscription = new Subscription;
                $subscription->name = $subscriptionPlan->name;
                $subscription->type = $subscriptionPlan->type;
                $subscription->period = $subscriptionPlan->period;
                $subscription->amount = $subscriptionPlan->price - $subscriptionPlan->discount;
                $subscription->license_amount = $subscriptionPlan->user_price;
                $subscription->license_discount = $subscriptionPlan->user_discount;
                $subscription->is_trial = BOOLEAN_TRUE;
                $subscription->company_id = $company->id;
                $subscription->status = BOOLEAN_TRUE;

                $start_date = Carbon::now();
                // Calculate number of days the trial is used
                $trial_days_used = $start_date->diffInDays($lastSubscription->start_date);
                if ($trial_days_used > 90) {
                    if ($lastSubscription->validity_days === 365) {
                        $trial_days_used = $trial_days_used > 365 ? 365 : $trial_days_used;
                    } else {
                        $trial_days_used = 90;
                    }
                }

                // Calculate new validity days
                // by subtracting trial duration used from annual trial validity days
                $new_validity_days = (int)($subscriptionPlan->validity_days - $trial_days_used);
                $end_date = $start_date->copy()->addDays($new_validity_days);

                // If Time is Past 6 am increase 1 day
                if ($start_date->setTimezone('Asia/Riyadh')->format('H') > 6) {
                    $end_date->addDays(1);
                }

                // $subscription->validity_days = $subscriptionPlan->validity_days;
                $subscription->validity_days = $new_validity_days;
                $subscription->start_date = $start_date->toDateString();
                $subscription->end_date = $end_date->toDateString();
                $subscription->save();

                // Update Company Status
                $company->status = COMPANY_STATUS_ACTIVE;
                $company->is_active = true;
                $company->save();

                if ($plan_type === PLAN_TYPE_PRO) {
                    // $users_count = $lastSubscription->user_licenses_count;
                    // Number of user licenses should be equal to number of devices
                    $users_count = $company->devices->count();

                    // Add Subscription User Licenses
                    $subscription_user_license = new SubscriptionUserLicense;
                    $subscription_user_license->quantity = $users_count;
                    $subscription_user_license->amount = ($subscription->license_amount - $subscription->license_discount) * $users_count;
                    $subscription_user_license->company_id = $company->id;
                    $subscription_user_license->subscription_id = $subscription->id;
                    $subscription_user_license->start_date = $start_date->toDateString();
                    $subscription_user_license->end_date = $end_date->toDateString();
                    $subscription_user_license->status = BOOLEAN_TRUE;
                    $subscription_user_license->save();

                    // Set business owner as machine user
                    $company->employees()->where('type', USER_TYPE_BUSINESS_OWNER)->update(['is_machine_user' => BOOLEAN_TRUE]);
                    // Set other employees as machine user as well
                    $employees_count = $company->activeEmployees()->count();
                    if ($users_count > 1 && $employees_count > 1) {
                        // employees count to set as machine user
                        $machine_employees_count = $employees_count < $users_count ? $employees_count - 1 : $users_count - 1;
                        $employees_to_deactivate = $company->employees()->where([
                            ['type', '=', USER_TYPE_EMPLOYEE],
                            ['is_active', '=', BOOLEAN_TRUE],
                        ])->orderBy('id', 'DESC')->limit($machine_employees_count)->get();
                        $employees_to_deactivate->each(function ($employee): void {
                            $employee->is_machine_user = true;
                            $employee->save();
                        });
                    }

                    // Generate licenses for employees who are not machine users
                    // Licenses payment will start after 90 days initial trial
                    $paid_licenses_emps_count = $employees_count - $users_count;
                    if ($paid_licenses_emps_count > 0) {
                        // if 90 days free trial is not over, start date should be after 90 days end
                        if ($trial_days_used < 90) {
                            $start_date = $start_date->addDays(90 - $trial_days_used);
                        }
                        $paid_subscription_user_license = new SubscriptionUserLicense;
                        $paid_subscription_user_license->quantity = $paid_licenses_emps_count;
                        // This amount is arbitrary.
                        // This should be updated once invoice is generated.
                        $paid_subscription_user_license->amount = ($subscription->license_amount - $subscription->license_discount) * $paid_licenses_emps_count;
                        $paid_subscription_user_license->company_id = $company->id;
                        $paid_subscription_user_license->subscription_id = $subscription->id;
                        $paid_subscription_user_license->start_date = $start_date->toDateString();
                        $paid_subscription_user_license->end_date = $end_date->toDateString();
                        $paid_subscription_user_license->status = BOOLEAN_TRUE;
                        $paid_subscription_user_license->save();

                        // If this license starts from today, generate invoice as well
                        if ($start_date->isToday()) {
                            // Active subscription has 0 values for licence amount & discount.
                            // So we will replicate the subscription plan
                            // and populate necessary values from subscription plan
                            $replicated_subscription = $subscription->replicate();
                            $related_subscription_plan = SubscriptionPlan::where('type', $subscription->type)
                                ->where('period', $subscription->period)
                                ->where('is_trial', BOOLEAN_FALSE)
                                ->first();
                            $replicated_subscription->license_amount = $related_subscription_plan->user_price;
                            $replicated_subscription->license_discount = $related_subscription_plan->user_discount;
                            $replicated_subscription->validity_days = $new_validity_days;
                            $invoice = Invoice::generateInvoice($replicated_subscription, INVOICE_TYPE_LICENSE, $paid_licenses_emps_count);
                            InvoiceGenerated::dispatch($invoice);

                            CrmLog::create([
                                'created_by' => auth()->id(),
                                'company_id' => $invoice->company_id,
                                'action' => 'created an invoice',
                            ]);
                        }
                    }
                }

                // Delete previous subscription and user licenses
                // $lastSubscription->userLicenses()->delete();
                // $lastSubscription->delete();

                // Generate Invoice
                if ($subscriptionPlan) {
                    // Generate Invoice
                    $devices = collect();
                    $company->devices->each(function ($device) use ($devices): void {
                        if ($device->invoices->count() === 0 || (! $device->isLastInvoiceThisMonth() && ! $device->isPaymentComplete())) {
                            $devices->push($device);
                        }
                    });

                    if ($devices->count() > 0) {
                        $invoice = Invoice::generateDevicePaymentInvoice($devices, $company->active_subscription->id);
                        InvoiceGenerated::dispatch($invoice);

                        CrmLog::create([
                            'created_by' => auth()->id(),
                            'company_id' => $invoice->company_id,
                            'action' => 'created device payment invoice invoice',
                        ]);
                    }
                }

                CrmLog::create([
                    'created_by' => auth()->id(),
                    'company_id' => $company->id,
                    'action' => 'activated annual trial subscription',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription Created Response.',
                    'data' => [
                        'subscription' => new SubscriptionResource($subscription),
                    ],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Company has already availed yearly trial subscription.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Company has no device.',
            'data' => null,
        ], 200);
    }

    /**
     * Extend trail subscription of the specified company.
     */
    public function extendTrialSubscription(Company $company): JsonResponse
    {
        $this->authorize('extendTrialSubscription', [Subscription::class, $company]);

        $lastSubscription = $company->subscriptions()->latest()->first();
        if ($lastSubscription) {
            $lastSubscription->validity_days = 365;
            $start_date = Carbon::parse($lastSubscription->start_date);
            $end_date = $start_date->copy()->addDays(365);
            $lastSubscription->end_date = $end_date->toDateString();
            $lastSubscription->save();

            foreach ($lastSubscription->userLicenses()->latest()->get() as $license) {
                $license->end_date = $end_date->toDateString();
                $license->save();
            }

            $company->crmLogs()->create([
                'created_by' => auth()->id(),
                'action' => 'extended trial subscription',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trial subscription extended to 1 year.',
                'data' => [
                    'subscription' => new SubscriptionResource($lastSubscription),
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid data.',
            'data' => null,
        ], 400);
    }
}

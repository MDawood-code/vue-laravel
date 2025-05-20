<?php

namespace App\Http\Controllers\API;

use App\Events\InvoiceGenerated;
use App\Http\Controllers\Controller;
use App\Http\Resources\BalanceResource;
use App\Http\Resources\CompanyUserBalanceDeductionResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UnifiedSubscriptions\UnifiedAddonResource;
use App\Http\Resources\UnifiedSubscriptions\UnifiedSubscriptionResource;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer
 *
 * @subgroup Subscription
 *
 * @subgroupDescription APIs for managing Subscription
 */
class SubscriptionsController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = $this->loggedInUser
            ->company
            ->subscriptions()
            ->where('status', BOOLEAN_TRUE)
            ->latest()
            ->get();

        $active_subscription = $this->loggedInUser->company->active_subscription;

        $requested_subscription = $this->loggedInUser->company->requested_subscription;

        // User license pricing; required when user wants to buy user licenses
        $user_license_pricing = $active_subscription ? $this->userLicensePricing($active_subscription) : null;

        $is_free_trial_over = true;
        // Determine if 90 days trial is over or not
        if (
            $active_subscription &&
            $active_subscription->is_trial == BOOLEAN_TRUE &&
            $active_subscription->type == PLAN_TYPE_PRO
        ) {
            $start_date = Carbon::now();
            if ($active_subscription->validity_days > 90) {
                $trial_days_used = $start_date->diffInDays($active_subscription->start_date);
                $trial_days_used = 365 - $active_subscription->validity_days + $trial_days_used;
                if ($trial_days_used < 90) {
                    $is_free_trial_over = false;
                }
            } else {
                $trial_days_used = $start_date->diffInDays($active_subscription->start_date);
                if ($trial_days_used < 90) {
                    $is_free_trial_over = false;
                }
            }
        }

        // Get active subscriptions and active addons
        $active_company_addons = $this->loggedInUser->company->activeAddons;
        $activeSubsAndAddons = collect();
        if ($active_subscription) {
            $activeSubsAndAddons = $activeSubsAndAddons->push(new UnifiedSubscriptionResource($active_subscription));
        }
        if ($active_company_addons->isNotEmpty()) {
            $activeSubsAndAddons = $activeSubsAndAddons->concat(UnifiedAddonResource::collection($active_company_addons));
        }

        // Get all subscriptions and addons
        $company_addons = $this->loggedInUser->company->addons()->with('addon')->where('status', BOOLEAN_TRUE)->get();
        $subsAndAddons = collect();
        if ($subscriptions->isNotEmpty()) {
            $subsAndAddons = $subsAndAddons->concat(UnifiedSubscriptionResource::collection($subscriptions));
        }
        if ($company_addons->isNotEmpty()) {
            $subsAndAddons = $subsAndAddons->concat(UnifiedAddonResource::collection($company_addons));
        }
        $subsAndAddons = $subsAndAddons->sortByDesc('created_at')->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Subscription List Response.',
            'data' => [
                'subscriptions' => SubscriptionResource::collection($subscriptions),
                'active_subscription' => $active_subscription ? new SubscriptionResource($active_subscription) : null,
                'requested_subscription' => $requested_subscription ? new SubscriptionResource($requested_subscription) : null,
                'user_license_pricing' => $user_license_pricing,
                'is_free_trial_over' => $is_free_trial_over,
                'balance' => $this->loggedInUser->company->balance ? new BalanceResource($this->loggedInUser->company->balance) : null,
                'balance_deductions_history' => $this->loggedInUser->company->balanceDeductions->isNotEmpty() ? CompanyUserBalanceDeductionResource::collection($this->loggedInUser->company->balanceDeductions) : null,
                'active_subscriptions_addons' => $activeSubsAndAddons,
                'subscriptions_addons' => $subsAndAddons,
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $active_subscription = $this->loggedInUser->company->active_subscription;
        if ($active_subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Already have an active subscription.',
                'data' => [],
            ]);
        }

        $subscriptionScheme = getSystemSubscriptionScheme();
        // Check if the subscription scheme is daily
        if ($subscriptionScheme && $subscriptionScheme === 'daily') {
            $plan_type = PLAN_TYPE_DAILY;
            $plan_period = PERIOD_DAILY;
        } else {
            $plan_type = $request->type ?? PLAN_TYPE_BASIC;
            $plan_period = $request->period ?? PERIOD_MONTHLY;
        }

        $users_count = $request->users_count ?? 1;

        $selected_plan = SubscriptionPlan::where('type', $plan_type)
            ->where('period', $plan_period)
            ->where('is_trial', BOOLEAN_FALSE)
            ->first();
        //TODO: Handle error if not found

        $daily_plan_amount = 0;
        $daily_license_discount = 0;
        $daily_required_amount_per_user = 0;
        // Write validation rule to check if request has type
        if ($request->has('type')) {
            $type = $request->integer('type');
            if ($type === PLAN_TYPE_DAILY) {
                // For daily subscription, discount is in percentage because the amount is not fixed.
                $daily_plan_amount = ($selected_plan->price - ($selected_plan->price * $selected_plan->discount / 100));
                $daily_license_discount = $selected_plan->user_price * $selected_plan->user_discount / 100;
                $daily_required_amount_per_user = ($daily_plan_amount / $users_count + $selected_plan->user_price - $daily_license_discount);
                // addons amount
                $active_addons_amount = $this->loggedInUser->company->addons()->where('status', BOOLEAN_TRUE)->get()->sum(fn ($addon): float => (float) ($addon->price - $addon->discount));
                // Check if balance is present and its value is >= $selected_plan price - discount / 100 * users count * month
                if ($request->has('balance') && $request->integer('balance') >= ($daily_required_amount_per_user * $users_count + $active_addons_amount) * 15) {
                    // Validation passed
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Balance must be greater than or equal to '.($daily_required_amount_per_user + $active_addons_amount) * 15 .' per user per month.',
                        'data' => [],
                    ]);
                }
            }
        }

        // Add Subscription

        // Daily Subscription
        if ($plan_type === PLAN_TYPE_DAILY) {
            $subscription = new Subscription;
            $subscription->name = $selected_plan->name;
            $subscription->type = $selected_plan->type;
            $subscription->period = $selected_plan->period;
            $subscription->amount = $daily_plan_amount;
            $subscription->license_amount = $selected_plan->user_price;
            $subscription->license_discount = $daily_license_discount;
            $subscription->balance = $request->integer('balance');
            $subscription->is_trial = BOOLEAN_FALSE;
            $subscription->status = BOOLEAN_TRUE;
            $subscription->validity_days = (int) ceil(($request->integer('balance') / $daily_required_amount_per_user) * $selected_plan->validity_days);
            $subscription->company_id = $this->loggedInUser->company->id;

            // Search for latest subscriptions to get the correct start_date
            $last_subscription = $this->loggedInUser->company->subscriptions()
                ->where('status', BOOLEAN_TRUE)
                ->orderByDesc('end_date')
                ->first();

            if ($last_subscription && Carbon::parse($last_subscription->end_date)->greaterThanOrEqualTo(Carbon::now())) {
                $start_date = Carbon::parse($last_subscription->end_date)->addDay();
            } else {
                $start_date = Carbon::now();
            }

            $subscription->start_date = $start_date->toDateString();
            $subscription->end_date = null;
            $subscription->save();

            // Add Subscription User Licenses
            $subscription_user_license = new SubscriptionUserLicense;
            $subscription_user_license->quantity = $users_count;
            $subscription_user_license->amount = $request->integer('balance');
            $subscription_user_license->company_id = $this->loggedInUser->company->id;
            $subscription_user_license->subscription_id = $subscription->id;
            $subscription_user_license->start_date = $start_date->toDateString();
            $subscription_user_license->end_date = null;
            $subscription_user_license->status = BOOLEAN_TRUE;
            $subscription_user_license->save();

        } else { //Monthly or annual subscription
            $subscription = new Subscription;
            $subscription->name = $selected_plan->name;
            $subscription->type = $selected_plan->type;
            $subscription->period = $selected_plan->period;
            $subscription->amount = $selected_plan->price - $selected_plan->discount;
            $subscription->license_amount = $selected_plan->user_price;
            $subscription->license_discount = $selected_plan->user_discount;
            $subscription->is_trial = BOOLEAN_FALSE;
            $subscription->validity_days = $selected_plan->validity_days;
            $subscription->company_id = $this->loggedInUser->company->id;

            // Search for latest subscriptions to get the correct start_date
            $last_subscription = $this->loggedInUser->company->subscriptions()
                ->where('status', BOOLEAN_TRUE)
                ->orderByDesc('end_date')
                ->first();

            if ($last_subscription && Carbon::parse($last_subscription->end_date)->greaterThanOrEqualTo(Carbon::now())) {
                $start_date = Carbon::parse($last_subscription->end_date)->addDay();
            } else {
                $start_date = Carbon::now();
            }
            $end_date = $start_date->copy()->addDays($subscription->validity_days);

            // If Time is Past 6 am increase 1 day
            if ($start_date->setTimezone('Asia/Riyadh')->format('H') > 6) {
                $end_date->addDays(1);
            }

            $subscription->start_date = $start_date->toDateString();
            $subscription->end_date = $end_date->toDateString();
            $subscription->save();

            // Update Company Status
            $subscription->company->status = COMPANY_STATUS_SUBSCRIPTION_IN_REVIEW;
            $subscription->company->save();

            if ($plan_type === PLAN_TYPE_PRO) {
                // Add Subscription User Licenses
                $subscription_user_license = new SubscriptionUserLicense;
                $subscription_user_license->quantity = $users_count;
                $subscription_user_license->amount = ($subscription->license_amount - $subscription->license_discount) * $users_count;
                $subscription_user_license->company_id = $this->loggedInUser->company->id;
                $subscription_user_license->subscription_id = $subscription->id;
                $subscription_user_license->start_date = $start_date->toDateString();
                $subscription_user_license->end_date = $end_date->toDateString();
                $subscription_user_license->status = BOOLEAN_FALSE;
                $subscription_user_license->save();
            }
        }

        // if active employees are more than requested licenses count, deactivate the extra employees
        $active_employees_count = $this->loggedInUser->company->activeEmployees()->count();
        if ($active_employees_count > $users_count) {
            $extra_active_emps_count = $active_employees_count - $users_count;
            $this->loggedInUser->company->activeEmployees()
                ->where('type', '!=', USER_TYPE_BUSINESS_OWNER)
                ->take($extra_active_emps_count)
                ->update(['is_active' => false]);
        }

        CrmLog::create([
            'company_id' => $this->loggedInUser->company->id,
            'action' => 'Company made a new subscription',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription Created Response.',
            'data' => ['subscription' => new SubscriptionResource($subscription)],
        ], 200);
    }

    /**
     * Renew a subscription.
     */
    public function renew(Request $request): JsonResponse
    {
        $this->authorize('renew', Subscription::class);

        // TODO: Do User wants to RENEW or Not

        // TODO: This method is not updated to reflect daily subscriptions

        // Get the Latest Subscription which Invoice is Paid
        $subscription = Subscription::latest()
            ->where('company_id', $this->loggedInUser->company_id)
            ->whereHas('invoice', fn ($query) => $query->where('status', INVOICE_STATUS_PAID))->first();

        if (! $subscription) {
            // Get Trial Subscription
            $subscription = Subscription::latest()
                ->where('company_id', $this->loggedInUser->company_id)
                ->where('is_trial', BOOLEAN_TRUE)->first();
        }

        if ($subscription) {
            if (Carbon::parse($subscription->end_date)->greaterThanOrEqualTo(Carbon::now())) {
                $start_date = Carbon::parse($subscription->end_date)->addDay();
            } else {
                $start_date = Carbon::now();
            }
            $end_date = $start_date->copy()->addDays($subscription->validity_days);

            $subscriptionCopy = $subscription->replicate()->fill([
                'is_trial' => BOOLEAN_FALSE,
                'start_date' => $start_date->toDateString(),
                'end_date' => $end_date->toDateString(),
            ]);

            // Push Subscription to DB
            $subscriptionCopy->push();

            $license_count = $subscription->user_licenses_count;
            $invoice = Invoice::generateInvoice($subscriptionCopy, INVOICE_TYPE_SUBSCRIPTION, $license_count);
            InvoiceGenerated::dispatch($invoice);

            if ($subscriptionCopy->type === PLAN_TYPE_PRO) {
                $license_amount = ($subscriptionCopy->license_amount - $subscriptionCopy->license_discount);
                $subscription_user_license = new SubscriptionUserLicense;
                $subscription_user_license->quantity = $license_count;
                $subscription_user_license->amount = $license_amount * $license_count;
                $subscription_user_license->start_date = $start_date->toDateString();
                $subscription_user_license->end_date = $end_date->toDateString();
                $subscription_user_license->company_id = $this->loggedInUser->company->id;
                $subscription_user_license->subscription_id = $invoice->subscription->id;
                $subscription_user_license->status = BOOLEAN_FALSE;
                $subscription_user_license->save();
            }

            // Push Subscription to DB
            $subscriptionCopy->save();

            CrmLog::create([
                'company_id' => $this->loggedInUser->company->id,
                'action' => 'Company renewed subscription',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription Renewed Successfully.',
                'data' => ['subscription' => new SubscriptionResource($subscriptionCopy)],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No Subscription Found.',
                'data' => [],
            ], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->authorize('delete', $subscription);

        // For Admin Only
        if ($subscription->status === BOOLEAN_TRUE) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription Can\'t be deleted, Invoice has been issued.',
                'data' => [],
            ]);
        } else {
            $subscription->delete();
            $subscription->userLicenses()->delete();

            CrmLog::create([
                'company_id' => $this->loggedInUser->company->id,
                'action' => 'Company deleted a subscription',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription Deleted Successfully',
                'data' => [],
            ]);
        }
    }

    /**
     * Get User License pricing having user license amount and user license discount
     *
     * @return array<mixed>
     */
    public function userLicensePricing(Subscription $subscription): array
    {
        $this->authorize('userLicensePricing', $subscription);

        $related_subscription_plan = SubscriptionPlan::where('type', $subscription->type)
            ->where('period', $subscription->period)
            ->where('is_trial', BOOLEAN_FALSE)
            ->first();
        if ($subscription->period === PERIOD_MONTHLY) {
            $license_amount = $related_subscription_plan->user_price;
            $license_discount = $related_subscription_plan->user_discount;
        } elseif ($subscription->period === PERIOD_DAILY) {
            // pricing for 1 month
            // discount for daily is in percentage
            $license_amount = $related_subscription_plan->user_price * 30;
            $license_discount = $related_subscription_plan->user_price * $related_subscription_plan->user_discount / 100 * 30;
        } else {
            // Calculate Yearly Amount
            $end_date = Carbon::createFromFormat('Y-m-d', $subscription->end_date);
            throw_if($end_date == false, new Exception("Invalid date format: {$subscription->end_date}"));
            $end_date = $end_date->endOfDay();

            $subscription_ending_in_days = Carbon::now()->startOfDay()->diffInDays($end_date);
            if ($subscription_ending_in_days >= 330) {
                $license_amount = $related_subscription_plan->user_price;
                $license_discount = $related_subscription_plan->user_discount;
            } else {
                $months = ceil($subscription_ending_in_days / 30);
                // Initially it was like this. But it seems wrong
                // $license_amount = $subscription->license_amount * $months;
                $license_amount = $related_subscription_plan->user_price / 12 * $months;
                $license_discount = 0;
            }
        }

        return [
            'license_amount' => $license_amount,
            'license_discount' => $license_discount,
        ];
    }
}

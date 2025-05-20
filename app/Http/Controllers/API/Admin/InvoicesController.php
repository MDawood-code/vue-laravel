<?php

namespace App\Http\Controllers\API\Admin;

use App\Events\InvoiceGenerated;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\CrmLog;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * @group Admin
 *
 * @subgroup Invoice
 *
 * @subgroupDescription APIs for managing Invoice
 */
class InvoicesController extends Controller
{
    private readonly ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(?int $company_id = null): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $company = $company_id ? Company::find($company_id) : $this->loggedInUser->company;

        $invoices = $company->invoices()->latest()->with(['payments' => function (Builder $query): void {
            $query->where('status', PAYMENT_STATUS_PAID);
        }])->get();

        return response()->json([
            'success' => true,
            'message' => 'Invoice List Response.',
            'data' => ['invoices' => InvoiceResource::collection($invoices)],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        // Generate Invoice for Requested Subscription
        $subscription = Subscription::where('status', BOOLEAN_FALSE)
            ->where('is_trial', BOOLEAN_FALSE)
            ->where('id', $request->subscription_id)
            ->first();

        $subscription_plan = SubscriptionPlan::where('type', $subscription->type)
            ->where('period', $subscription->period)
            ->where('is_trial', BOOLEAN_FALSE)
            ->first();

        if ($subscription && $subscription_plan) {
            // Generate Invoice
            $invoice = Invoice::generateInvoice($subscription, INVOICE_TYPE_SUBSCRIPTION);
            InvoiceGenerated::dispatch($invoice);

            CrmLog::create([
                'created_by' => auth()->id(),
                'company_id' => $invoice->company_id,
                'action' => 'created an invoice',
            ]);

            // Send Success Response
            return response()->json([
                'success' => true,
                'message' => 'Invoice has been generated.',
                'data' => ['invoice' => $invoice],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invoice data not found.',
                'data' => [],
            ]);
        }
    }

    /**
     * Generate invoice license
     */
    public function generateLicenseInvoice(Request $request): JsonResponse
    {
        $this->authorize('generateLicenseInvoice', Invoice::class);

        if (! $request->users_count) {
            return response()->json([
                'success' => false,
                'message' => 'Users Count not found.',
                'data' => [],
            ]);
        }

        $user = auth()->guard('api')->user();

        if ($user) {
            $active_subscription = $user->company->activeSubscription;
            $license_count = $request->users_count;

            // if pro (or daily) trial subscription, increase user licenses for free
            // it will be free if validity days <= 90 because it will be trial.
            $free_licenses = 0;
            $paid_licenses = 0;
            if (
                $active_subscription &&
                $active_subscription->is_trial == BOOLEAN_TRUE &&
                ($active_subscription->type == PLAN_TYPE_PRO || $active_subscription->type == PLAN_TYPE_DAILY) &&
                $active_subscription->validity_days <= 90
            ) {
                if ($subscription_user_license = $active_subscription->userLicenses()->latest()->first()) {
                    $subscription_user_license->quantity += $license_count;
                    $subscription_user_license->save();
                    $free_licenses = $license_count;

                    // Send Success Response
                    return response()->json([
                        'success' => true,
                        'free_licenses' => $free_licenses,
                        'paid_licenses' => $paid_licenses,
                        'message' => 'User Licenses added for trial subscription.',
                        'data' => [],
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'You do not have active pro trial.',
                    'data' => [],
                ]);
            }

            // if daily subscription, no need to purchase license.
            // Directly increase licenses count in subscription
            // Amount is already handled by daily deducting amount per user basis
            if (
                $active_subscription &&
                $active_subscription->type == PLAN_TYPE_DAILY
            ) {
                if ($subscription_user_license = $active_subscription->userLicenses()->latest()->first()) {
                    $subscription_user_license->quantity += $license_count;
                    $subscription_user_license->save();
                    $paid_licenses = $license_count;

                    // Send Success Response
                    return response()->json([
                        'success' => true,
                        'free_licenses' => $free_licenses,
                        'paid_licenses' => $paid_licenses,
                        'message' => 'User Licenses added.',
                        'data' => [],
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'You do not have active daily subscription.',
                    'data' => [],
                ]);
            }

            // if user has devices and yearly trial subscription having a total of 365 days validity
            // additional licenses should be bought and are not free
            if (
                $active_subscription &&
                $active_subscription->is_trial == BOOLEAN_TRUE &&
                $active_subscription->type == PLAN_TYPE_PRO &&
                $active_subscription->validity_days > 90
            ) {
                // Add licenses
                // If 90 days trial is not over, license will start after 90 days trial
                // otherwise it will start from today
                $start_date = Carbon::now();
                $trial_days_used = (int)$start_date->diffInDays($active_subscription->start_date);
                $trial_days_used = 365 - $active_subscription->validity_days + $trial_days_used;
                if ($trial_days_used < 90) {
                    $start_date = $start_date->addDays(90 - $trial_days_used);
                }
                $paid_subscription_user_license = new SubscriptionUserLicense;
                $paid_subscription_user_license->quantity = $license_count;
                // This amount is arbitrary.
                // This should be updated once invoice is generated.
                $paid_subscription_user_license->amount = ($active_subscription->license_amount - $active_subscription->license_discount) * $license_count;
                $paid_subscription_user_license->company_id = $user->company->id;
                $paid_subscription_user_license->subscription_id = $active_subscription->id;
                $paid_subscription_user_license->start_date = $start_date->toDateString();
                $paid_subscription_user_license->end_date = $active_subscription->end_date;
                $paid_subscription_user_license->status = BOOLEAN_TRUE;
                $paid_subscription_user_license->save();

                $data = [];
                // else if 90 days trial is over, generate invoice as well.
                if ($start_date->isToday()) {
                    // Active subscription has 0 values for licence amount & discount.
                    // So we will replicate the subscription plan
                    // and populate necessary values from subscription plan
                    $replicated_subscription = $active_subscription->replicate();
                    $related_subscription_plan = SubscriptionPlan::where('type', $active_subscription->type)
                        ->where('period', $active_subscription->period)
                        ->where('is_trial', BOOLEAN_FALSE)
                        ->first();
                    $replicated_subscription->license_amount = $related_subscription_plan->user_price;
                    $replicated_subscription->license_discount = $related_subscription_plan->user_discount;
                    $replicated_subscription->validity_days = 365 - $trial_days_used;
                    $invoice = Invoice::generateInvoice($replicated_subscription, INVOICE_TYPE_LICENSE, $license_count);
                    InvoiceGenerated::dispatch($invoice);

                    CrmLog::create([
                        'created_by' => auth()->id(),
                        'company_id' => $invoice->company_id,
                        'action' => 'created license invoice',
                    ]);

                    $data = ['invoice' => $invoice];
                }

                $paid_licenses = $license_count;

                // Send Success Response
                return response()->json([
                    'success' => true,
                    'free_licenses' => $free_licenses,
                    'paid_licenses' => $paid_licenses,
                    'message' => 'License(s) have been added.',
                    'data' => $data,
                ]);
            }

            if ($active_subscription && $active_subscription->type === PLAN_TYPE_PRO) {
                $invoice = Invoice::generateInvoice($active_subscription, INVOICE_TYPE_LICENSE, $license_count);
                InvoiceGenerated::dispatch($invoice);

                CrmLog::create([
                    'created_by' => auth()->id(),
                    'company_id' => $invoice->company_id,
                    'action' => 'created an invoice',
                ]);

                $paid_licenses = $license_count;

                // Send Success Response
                return response()->json([
                    'success' => true,
                    'free_licenses' => $free_licenses,
                    'paid_licenses' => $paid_licenses,
                    'message' => 'Invoice has been generated.',
                    'data' => ['invoice' => $invoice],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Active Subscription not found.',
                    'data' => [],
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => [],
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'success' => true,
            'message' => 'Invoice Response',
            'data' => [
                'invoice' => new InvoiceResource($invoice->load('payments')),
            ],
        ]);
    }

    /**
     * Show invoice template
     *
     * @unauthenticated
     */
    public function showTemplate(Company $company, Invoice $invoice): View
    {
        abort_if($invoice->company_id !== $company->id, 404);

        return view('invoice', [
            'invoice' => $invoice,
            'invoice_details' => $invoice->details,
            'company' => $company,
        ]);
    }

    /**
     * Generate Devices Payment Invoice
     */
    public function generateDevicesPaymentInvoice(Company $company): JsonResponse
    {
        $this->authorize('generateDevicesPaymentInvoice', Invoice::class);

        if (! $company->active_subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Activate Annual subscription first.',
                'data' => [],
            ]);
        }

        $user = auth()->guard('api')->user();

        if ($user) {
            $company->load('devices');

            // if no invoices OR (no invoice this month AND payment is not complete)
            // Generate invoice for this month

            $devices = collect();
            $messages = collect();

            $company->devices->each(function ($device) use ($devices, $messages): void {
                if ($device->invoices->count() === 0 || (! $device->isLastInvoiceThisMonth() && ! $device->isPaymentComplete())) {
                    $devices->push($device);
                } elseif ($device->isLastInvoiceThisMonth()) {
                    $messages->push(['id' => $device->id, 'message' => 'This month invoice has already been generated.']);
                } elseif ($device->isPaymentComplete()) {
                    $messages->push(['id' => $device->id, 'message' => 'This device payment has been completed.']);
                }
            });

            if ($devices->count() > 0) {
                $invoice = Invoice::generateDevicePaymentInvoice($devices, $company->active_subscription->id);
                InvoiceGenerated::dispatch($invoice);
                $invoice = new InvoiceResource($invoice);
                $message = 'Invoice has been generated.';
                $status = true;

                CrmLog::create([
                    'created_by' => auth()->id(),
                    'company_id' => $invoice->company_id,
                    'action' => 'generated device payment invoice',
                ]);
            } else {
                $invoice = null;
                $status = false;
                $message = $messages->last()['message'];
            }

            // Send Success Response
            return response()->json([
                'success' => $status,
                'message' => $message,
                'data' => ['invoice' => $invoice],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => [],
            ]);
        }
    }

    /**
     * Display Company Device Invoices
     */
    public function deviceInvoices(Device $device): JsonResponse
    {
        $this->authorize('deviceInvoices', [Invoice::class, $device]);

        $invoices = $device->invoices()->latest()->with(['payments' => function (Builder $query): void {
            $query->where('status', PAYMENT_STATUS_PAID);
        }])->get();

        return response()->json([
            'success' => true,
            'message' => 'Invoice List Response.',
            'data' => ['invoices' => InvoiceResource::collection($invoices)],
        ]);
    }

    /**
     * Mark invoice as paid
     */
    public function markInvoiceAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('markInvoiceAsPaid', $invoice);

        $validator = Validator::make($request->all(), [
            'manually_paid_reason' => [
                'required',
                'string',
                'min:5',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Some error occurred.',
                'data' => [
                    'errors' => $validator->messages()->toArray(),
                ],
            ], 400);
        }

        $invoice->status = INVOICE_STATUS_PAID;
        $invoice->mark_as_paid_by = (int) auth()->id();
        $invoice->manually_paid_reason = $request->manually_paid_reason;
        $invoice->save();
        $invoice->company()->update(['status' => COMPANY_STATUS_ACTIVE, 'is_active' => true]);

        if (getSystemSubscriptionScheme() === 'daily') {
            $balance = $invoice->company->balance;
            // Check if the subscription balance exists
            if ($balance) {
                $extensionDays = $invoice->type === INVOICE_TYPE_SUBSCRIPTION && $invoice->subscription?->type === PLAN_TYPE_DAILY ? $invoice->subscription->validity_days : ceil($invoice->amount_charged * 2);
                // Update the amount of the subscription balance
                $balance->amount += $invoice->details()->whereIn('type', [INVOICE_DETAIL_TYPE_SUBSCRIPTION, INVOICE_DETAIL_TYPE_BALANCE_TOPUP])->latest()->first()?->amount;
                if (Carbon::parse($balance->expiry_date)->greaterThanOrEqualTo(Carbon::now())) {
                    $balance->expiry_date = Carbon::parse($balance->expiry_date)->addDays((int) $extensionDays);
                } else {
                    $balance->expiry_date = Carbon::now()->addDays((int) $extensionDays);
                }
                $balance->save();
            } else {
                // Create a new subscription balance with the requested balance
                $invoice->company->balance()->create([
                    'amount' => $invoice->details()->whereIn('type', [INVOICE_DETAIL_TYPE_SUBSCRIPTION, INVOICE_DETAIL_TYPE_BALANCE_TOPUP])->latest()->first()?->amount,
                    'expiry_date' => Carbon::now()->addDays($invoice->subscription->validity_days),
                ]);
            }
        }

        CrmLog::create([
            'created_by' => auth()->id(),
            'company_id' => $invoice->company_id,
            'action' => 'marked invoice as paid',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice Marked as Paid.',
            'data' => ['invoice' => new InvoiceResource($invoice)],
        ]);
    }
}

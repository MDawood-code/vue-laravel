<?php

namespace App\Http\Controllers\API;

use App\Events\PaymentVerified;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreparePaymentCheckoutRequest;
use App\Http\Requests\TopUpBalanceRequest;
use App\Http\Traits\ApiResponseHelpers;
use App\Models\CompanyAddon;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use App\UtilityClasses\PaymentMethod;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * @group Customer
 *
 * @subgroup Payment
 *
 * @subgroupDescription APIs for managing Payment
 */
class PaymentController extends Controller
{
    use ApiResponseHelpers;

    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Prepare checkout.
     */
    public function checkoutRequest(PreparePaymentCheckoutRequest $request): JsonResponse
    {
        return $this->prepareCheckout($request);
    }

    /**
     * Verify payment.
     *
     * @bodyParam checkoutID string required The checkoutID. Example: 'kjk@kdifud232311'.
     */
    public function verify(Request $request): JsonResponse
    {
        if ($request->has('checkoutID') && $request->checkoutID !== null) {
            $payment = Payment::where('checkout_id', $request->checkoutID)->first();
            if ($payment) {
                try {
                    // Get Integer ID from Text
                    $payment_brand = PAYMENT_BRAND_VISA;
                    if ($payment->brand === 'MADA') {
                        $payment_brand = PAYMENT_BRAND_MADA;
                    } elseif ($payment->brand === 'MASTER') {
                        $payment_brand = PAYMENT_BRAND_MASTER;
                    }

                    $paymentMethod = new PaymentMethod($payment_brand);
                    $response = $paymentMethod->verify($request->checkoutID);
                    $body = $response->getBody();

                    /** @var stdClass $responseObj * */
                    $responseObj = json_decode($body);
                    if (isset($responseObj->id)) {
                        $payment->reference_id = $responseObj->id;
                        $payment->result = json_encode($responseObj->result ?? []) ?: null;
                        $payment->result_details = json_encode($responseObj->resultDetails ?? []) ?: null;

                        if (
                            $this->isPaymentSuccessfull($responseObj->result->code)
                            && $responseObj->paymentType === $payment->type
                            && $responseObj->paymentBrand === $payment->brand
                            && (float) $responseObj->amount === $payment->amount
                            && $responseObj->currency === $payment->currency
                        ) {
                            // Mark this Payment as PAID
                            $payment->status = PAYMENT_STATUS_PAID;
                            // Mark this Invoice as PAID as well
                            $invoice = Invoice::find($payment->invoice_id);
                            // if no invoice, it means the payment is for topup
                            // so create invoice for it
                            if (! $invoice) {
                                // Formula to get original amount excluding tax: $payment->amount / (1 + TAX_PERCENTAGE)
                                $invoice = Invoice::generateTopUpBalanceInvoice($payment->amount / (1 + TAX_PERCENTAGE), $this->loggedInUser->company->id);
                                $payment->invoice_id = $invoice->id;
                            }

                            // Generate Invoice ID
                            if (empty($invoice->uid)) {
                                $invoice->uid = Invoice::generateInvoiceUID();
                            }

                            // Add a new Subscription with users count
                            if ($invoice->type === INVOICE_TYPE_LICENSE) {
                                $license_details = $invoice->details()->where('type', INVOICE_DETAIL_TYPE_LICENSE)->first();
                                $subscription_user_license = new SubscriptionUserLicense;
                                $subscription_user_license->quantity = $license_details->quantity;
                                $subscription_user_license->amount = $invoice->subscription->license_amount - $invoice->subscription->license_discount;
                                $subscription_user_license->start_date = Carbon::now()->toDateString();
                                $subscription_user_license->end_date = $invoice->subscription->end_date;
                                $subscription_user_license->company_id = $this->loggedInUser->company->id;
                                $subscription_user_license->subscription_id = $invoice->subscription->id;
                                $subscription_user_license->status = BOOLEAN_TRUE;
                                $subscription_user_license->save();
                            } elseif ($invoice->type === INVOICE_TYPE_SUBSCRIPTION) {
                                // Update Company Account If Current Subscription is Updated
                                //                                $start_date = Carbon::createFromFormat('Y-m-d', $invoice->subscription->start_date)->startOfDay();
                                //                                $end_date = Carbon::createFromFormat('Y-m-d', $invoice->subscription->end_date)->endOfDay();

                                // Update Invoice Licenses Status
                                foreach ($invoice->subscription->userLicenses()->get() as $license) {
                                    $license->status = BOOLEAN_TRUE;
                                    $license->save();
                                }

                                // if daily subscription, update company balance amount
                                if ($invoice->subscription->type === PLAN_TYPE_DAILY) {
                                    $balance = $invoice->company->balance;

                                    // Update the amount of the subscription balance
                                    $balance->amount += $invoice->details()->where('type', INVOICE_DETAIL_TYPE_SUBSCRIPTION)->latest()->first()?->amount;
                                    if (Carbon::parse($balance->expiry_date)->greaterThanOrEqualTo(Carbon::now())) {
                                        $balance->expiry_date = Carbon::parse($balance->expiry_date)->addDays($invoice->subscription->validity_days);
                                    } else {
                                        $balance->expiry_date = Carbon::now()->addDays($invoice->subscription->validity_days);
                                    }
                                    $balance->save();
                                }
                            } elseif ($invoice->type === INVOICE_TYPE_DEVICE_PAYMENT) {
                                //TODO: Anything for Device Invoices here
                            } elseif ($invoice->type === INVOICE_TYPE_ADDON) {
                                $companyAddon = CompanyAddon::findOrFail($invoice->company_addon_id);
                                $companyAddon->status = BOOLEAN_TRUE;
                                $companyAddon->start_date = Carbon::now()->toDateString();
                                if ($companyAddon->addon->billing_cycle === ADDON_BILLING_MONTHLY) {
                                    $companyAddon->end_date = Carbon::now()->addMonth()->toDateString();
                                } else {
                                    $companyAddon->end_date = Carbon::now()->addYear()->toDateString();
                                }
                                $companyAddon->save();
                            } elseif ($invoice->type === INVOICE_TYPE_BALANCE_TOPUP) {
                                $balance = $invoice->company->balance;
                                // Update the amount of the subscription balance
                                $balance->amount += $invoice->details()->where('type', INVOICE_DETAIL_TYPE_BALANCE_TOPUP)->latest()->first()?->amount;
                                if (Carbon::parse($balance->expiry_date)->greaterThanOrEqualTo(Carbon::now())) {
                                    $balance->expiry_date = Carbon::parse($balance->expiry_date)->addDays((int) ceil($invoice->amount_charged * 2));
                                } else {
                                    $balance->expiry_date = Carbon::now()->addDays((int) ceil($invoice->amount_charged * 2));
                                }
                                $balance->save();
                            }

                            // Update Company Status
                            if ($invoice->type !== INVOICE_TYPE_ADDON) {
                                $invoice->company->status = COMPANY_STATUS_ACTIVE;
                                $invoice->company->save();
                            }

                            $invoice->status = INVOICE_STATUS_PAID;
                            $invoice->save();
                            $payment->save();

                            PaymentVerified::dispatch($payment);

                            CrmLog::create([
                                'company_id' => $invoice->company->id,
                                'action' => 'Payment was done',
                            ]);
                        } else {
                            $payment->status = PAYMENT_STATUS_DECLINE;
                            $payment->save();
                        }

                        return response()->json($responseObj);
                    } else {
                        // Error Case
                        return response()->json($responseObj);
                    }
                } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment Error Response.',
                        'data' => [
                            'error' => $e->getMessage(),
                        ],
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No Payment Found.',
                    'data' => [],
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Checkout ID Missing.',
                'data' => [],
            ]);
        }
    }

    /**
     * Topup balance.
     */
    public function topUpBalance(TopUpBalanceRequest $request): JsonResponse
    {
        if (getSystemSubscriptionScheme() === 'daily') {
            $daily_billing_amount = 0.0;
            $daily_subscription_amount = 0.0;
            $active_subscription = $this->loggedInUser->company?->active_subscription;
            if ($active_subscription && $active_subscription->is_trial === BOOLEAN_FALSE) {
                // For daily subscription, discount is in percentage because the amount is not fixed.
                $daily_subscription_amount = ($active_subscription->amount / $this->loggedInUser->company->activeEmployees()->count() + $active_subscription->license_amount - $active_subscription->license_discount);
                $daily_billing_amount += $daily_subscription_amount * $this->loggedInUser->company->activeEmployees()->count();
            }

            // addons amount
            $active_addons_amount = $this->loggedInUser->company->addons()->hasNoTrial()->where('status', BOOLEAN_TRUE)->get()->sum(fn ($addon): float => (float) ($addon->price - $addon->discount));
            $daily_billing_amount += $active_addons_amount;
            // Check if balance is present and its value is >= $selected_plan price - discount / 100 * users count * month
            if ($request->has('amount') && $request->float('amount') >= ($daily_billing_amount * 15 - $this->loggedInUser->company?->balance?->amount)) {
                // Validation passed
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Balance must be greater than or equal to '.($daily_billing_amount * 15 - $this->loggedInUser->company?->balance?->amount).'.',
                    'data' => [],
                ]);
            }
        }

        if ((int) $request->brand === PAYMENT_BRAND_STCPAY) {
            $invoice = Invoice::generateTopUpBalanceInvoice($request->amount, $this->loggedInUser->company->id);

            $invoice->stcpay_reference_id = $request->stcpay_reference_id;
            $invoice->save();

            $this->loggedInUser->company->crmLogs()->create([
                'created_by' => auth()->id(),
                'action' => 'made topup request of amount SAR '.$request->amount,
            ]);

            return $this->respondWithSuccess([
                'success' => true,
                'message' => 'Top-Up balance invoice generated successfully.',
                'data' => [],
            ]);
        }

        $payableAmount = Invoice::calculateTopupBalanceInvoiceAmount($request->float('amount'));

        // Now proceed with the payment process
        $request->merge(['amount' => $payableAmount]);

        return $this->prepareCheckout($request);
    }

    private function prepareCheckout(Request $request): JsonResponse
    {
        if ($request->has('brand') && $request->brand !== null) {
            // Find Invoice
            /** @var ?Invoice $invoice */
            $invoice = Invoice::find($request->invoice_id);
            // If Invoice Not Found (in case of topup) or found and Belongs to Authenticated User Company
            if (! $invoice || ($this->loggedInUser->company->id === $invoice->company_id
                && $invoice->status === INVOICE_STATUS_UNPAID)) {
                //Default Brand VISA
                $brand = 'VISA';
                if ((int) $request->brand === PAYMENT_BRAND_MADA) {
                    $brand = 'MADA';
                } elseif ((int) $request->brand === PAYMENT_BRAND_MASTER) {
                    $brand = 'MASTER';
                }

                // payable amount
                if ($invoice) {
                    $payable_amount = $invoice->amount_charged;
                    $transaction_id_ref = $invoice->id;
                    $company = $invoice->company;
                } else {
                    $payable_amount = $request->amount;
                    $transaction_id_ref = 'PREINVOICE'.$this->loggedInUser->company->id; // just to make it unique
                    $company = $this->loggedInUser->company;
                }

                // Create Payment with its details
                $payment = new Payment;
                $payment->invoice_id = $invoice?->id;
                $payment->type = config('payment.type');
                $payment->brand = $brand;
                $payment->amount = config('payment.mode') === 'TEST' ? ceil($payable_amount) : $payable_amount;
                $payment->currency = config('payment.currency');
                $payment->merchant_transaction_id = Str::padLeft(strval($transaction_id_ref), 5, '0').date('Ymdhis');
                $payment->test_mode = config('payment.mode') === 'TEST' ? BOOLEAN_TRUE : BOOLEAN_FALSE;
                $payment->save();

                $paymentMethod = new PaymentMethod((int) $request->brand);
                try {
                    $response = $paymentMethod->checkout(
                        [
                            'amount' => number_format((float) $payment->amount, 2, '.', ''),
                            'merchantTransactionId' => $payment->merchant_transaction_id,
                            'customerEmail' => $company->owner->email,
                            'billingAddress' => $company->billing_address,
                            'billingCity' => $company->city->name_en,
                            'billingState' => $company->billingState->name_en,
                            'billingCountry' => 'SA', // TODO: Take Country from Frontend
                            'billingPostCode' => $company->billing_post_code,
                            'billingFirstName' => $company->owner->first_name,
                            'billingLastName' => $company->owner->last_name,
                        ]
                    );

                    $body = $response->getBody();
                    $responseObj = json_decode($body);
                    $payment->checkout_id = $responseObj->id;
                    $payment->save();
                    Log::debug('Payment Response: '.$body);

                    return response()->json($responseObj);
                } catch (Throwable $th) {
                    return response()->json([
                        'success' => false,
                        'message' => $th->getMessage(),
                        'data' => [],
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice Not Found.',
                    'data' => [],
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Checkout Preparation has some Issues.',
                'data' => [],
            ]);
        }
    }

    private function isPaymentSuccessfull(mixed $code): bool
    {
        $result_codes = ['000.000.000', '000.000.100'];
        // When in Test Mode
        if (config('payment.mode') === 'TEST') {
            $result_codes[] = '000.100.112';
            $result_codes[] = '000.100.110';
        }

        return in_array($code, $result_codes);
    }
}

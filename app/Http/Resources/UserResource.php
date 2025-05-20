<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\Company;
use App\Models\CustomFeature;
use App\Models\HelpdeskTicket;
use App\Models\ProductCategory;
use App\Models\ResellerBankDetail;
use App\Models\ResellerComment;
use App\Models\ResellerLevelConfiguration;
use App\Models\ResellerPayoutHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin User **/
class UserResource extends JsonResource
{
    protected ?User $user;

    public function __construct($resource, protected string $access_token = '', protected bool $add_products = false)
    {
        $this->resource = $resource;
        $this->user = auth()->guard('api')->user();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Get All Categories of current User to send in response with products
        $product_categories = ProductCategory::where('company_id', $this->company_id)
            ->orderBy('order')
            ->orderBy('name')
            ->get()->keyBy->id;

        if (! user_is_admin() && ! user_is_staff() && ! user_is_super_admin() && ! empty($this->company)) {
            $subscriptions = $this->company->subscriptions()->latest()->get();

            // Get Last Subscription Ending Date
            $last_subscription = $this->company->subscriptions()->latest()->first();
            if ($last_subscription) {
                $subscription_end_date = Carbon::parse($last_subscription->end_date)->addDay();
            } else {
                $subscription_end_date = Carbon::parse($this->created_at)->addDays(15);
            }
        } else {
            $subscription_end_date = Carbon::now()->addDays(1000);
            $subscriptions = [];
        }

        $app_config = json_decode($this->app_config);

        $response_array = [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'app_config' => $app_config,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
        if ($this->type == USER_TYPE_RESELLER) {
            $response_array['status'] = $this->status;
            $response_array['rejection_reason'] = $this->status === 3 ? $this->rejection_reason : null;
        } else {
            $response_array['is_active'] = (bool) $this->is_active;
        }
        if ($this->company) {
            $response_array['company_id'] = $this->company_id;
            $response_array['branch_id'] = $this->branch_id;
            $response_array['subscriptions'] = $subscriptions;
            $response_array['subscription_end_date'] = $subscription_end_date;
            $response_array['logo'] = $this->company->logo ? asset($this->company->logo) : null;
            $response_array['business_name'] = $this->company->name;
            $response_array['business_type'] = $this->company->business_type;
            $response_array['business_type_verification'] = new BusinessTypeVerificationResource($this->company->businessTypeVerification);
            $response_array['cr'] = $this->company->cr;
            $response_array['cr_certificate'] = $this->company->cr_certificate ? asset($this->company->cr_certificate) : null;
            $response_array['vat'] = $this->company->vat;
            $response_array['vat_certificate'] = $this->company->vat_certificate ? asset($this->company->vat_certificate) : null;
            $response_array['is_vat_exempt'] = (bool) $this->company->is_vat_exempt;
            if (user_is_admin_or_staff() || user_is_super_admin()) {
                $response_array['cr_verification'] = $this->company->cr_verification ? asset($this->company->cr_verification) : null;
                $response_array['vat_verification'] = $this->company->vat_verification ? asset($this->company->vat_verification) : null;
            }
            $response_array['branch_name'] = $this->branch->name;
            $response_array['address'] = $this->branch->address;
            $response_array['active_addons'] = CompactCompanyAddonResource::collection($this->company->activeAddons()->with('addon')->get());
            // $response_array['selected_addons'] = CompactCompanyAddonResource::collection($this->company->selectedAddons()->with('addon')->get());
            $response_array['round_off'] = (bool) $this->company->round_off;
            $response_array['is_billing_add_same_as_postal'] = (bool) $this->company->is_billing_add_same_as_postal;
            $response_array['billing_address'] = $this->company->billing_address;
            $response_array['billing_city'] = new CityResource($this->company->city);
            $response_array['billing_state'] = new RegionResource($this->company->billingState);
            $response_array['billing_country'] = $this->company->billing_country;
            $response_array['billing_post_code'] = $this->company->billing_post_code;
            $response_array['company_status'] = $this->company->status;
            $response_array['is_company_active'] = (bool) $this->company->is_active;
            $response_array['offline_availablity'] = $this->company->addons &&
            in_array('multi_branch', json_decode($this->company->addons)) ? false : true;
            $response_array['is_machine_user'] = $this->is_machine_user;
            $response_array['preferred_contact_time'] = $this->preferred_contact_time;
            $response_array['can_add_edit_product'] = (bool) $this->can_add_edit_product;
            $response_array['can_add_edit_customer'] = (bool) $this->can_add_edit_customer;
            $response_array['can_add_pay_sales_invoice'] = (bool) $this->can_add_pay_sales_invoice;
            $response_array['can_view_sales_invoice'] = (bool) $this->can_view_sales_invoice;
            $response_array['can_view_customer'] = (bool) $this->can_view_customer;
            $response_array['can_refund_transaction'] = (bool) $this->can_refund_transaction;
            $response_array['can_request_stock_adjustment'] = (bool) $this->can_request_stock_adjustment;
            $response_array['allow_discount'] = (bool) $this->allow_discount;
            $response_array['can_see_transactions'] = (bool) $this->can_see_transactions;
            $response_array['is_waiter'] = (bool) $this->is_waiter;
            $response_array['can_request_stock_transfer'] = (bool) $this->can_request_stock_transfer;
            $response_array['can_approve_stock_transfer'] = (bool) $this->can_approve_stock_transfer;
            $response_array['external_integrations'] = ExternalIntegrationResource::collection($this->company->externalIntegrations);
            $response_array['odoo_reference_id'] = $this->odoo_reference_id;
            $response_array['is_onboarding_complete'] = $this->company->is_onboarding_complete;
            $response_array['last_active_at'] = getHumanReadableDateInDays(                $this->company->last_active_at ? Carbon::parse($this->company->last_active_at) : null
        );
        }
        if ($this->type === USER_TYPE_RESELLER) {
            $resellerConfig = ResellerLevelConfiguration::where('reseller_id', $this->id)
                ->first();
            $reseller_pay_his = ResellerPayoutHistory::where('reseller_id', $this->id)
                ->get();
            $comments = ResellerComment::where('reseller_id', $this->id)
                ->get();
            $reseller_bank_det = ResellerBankDetail::where('reseller_id', $this->id)
                ->first();
            $totalCommissionAmount = 0;
            $resellerNumber = $this->reseller_number;
            // Check if the reseller configuration is found then Automatically change Level after one year
            if ($resellerConfig) {
                $proTarget = $resellerConfig->pro_target;
                $proRetainRate = $resellerConfig->pro_retain_rate;

                // Get the reseller's latest payout date and current date
                $oneYearAgo = Carbon::now()->subYear()->toDateString();
                $currentDate = Carbon::now()->toDateString();

                // Ensure $resellerNumber is set correctly
                $resellerNumber = $this->reseller_number;
                $reseller = User::where('id', $this->id)->first();
                if (! $reseller->reseller_level_change_at || $reseller->reseller_level_change_at <= $oneYearAgo) {
                    // Fetch the number of paid companies registered by the reseller in the last year
                    $totalCompaniesRegistered = Company::where('reseller_number', $resellerNumber)
                        ->where('companies.created_at', '>=', $oneYearAgo)
                        ->where('companies.created_at', '<=', $currentDate)
                        ->whereHas('subscriptions', function ($query): void {
                            $query->where('subscriptions.is_trial', BOOLEAN_FALSE);
                        })
                        ->count();

                    // Retain Customers
                    $retainedCompanies = Company::where('reseller_number', $resellerNumber)
                        ->where('companies.created_at', '>=', $oneYearAgo)
                        ->where('companies.created_at', '<=', $currentDate)
                        ->where('status', '!=', COMPANY_STATUS_BLOCKED)
                        ->whereHas('subscriptions', function ($query): void {
                            $query->where('subscriptions.is_trial', BOOLEAN_FALSE);
                        })
                        ->count();

                    // Calculate the retention rate
                    $retentionRate = $totalCompaniesRegistered > 0 ? ($retainedCompanies / $totalCompaniesRegistered) * 100 : 0;

                    // Check if the reseller meets the criteria

                    if ($totalCompaniesRegistered >= $proTarget && $retentionRate >= $proRetainRate) {
                        // Update the reseller's level
                        $reseller->reseller_level = 'Pro';
                    } else {
                        $reseller->reseller_level = 'Basic';
                    }
                    $reseller->reseller_level_change_at = $currentDate;
                    $reseller->save();

                }
            } else {
                // Handle the case where the reseller configuration is not found
                // For example, you can log an error or set a default level
                $reseller = User::where('id', $this->id)->first();
                $reseller->reseller_level = 'Basic';
                $reseller->save();
            }
            // Determine the latest payout date if payout history is not empty
            $latestPayoutDate = $this->resellerPayoutHistory->isNotEmpty()
            ? Carbon::parse($this->resellerPayoutHistory->max('date'))
            : null;
            // Define the query to calculate the total amount
            $totalAmountQuery = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_FALSE);
                })
                ->where('is_active', BOOLEAN_TRUE)
                ->join('company_user_balance_deductions', 'companies.id', '=', 'company_user_balance_deductions.company_id');

            // Dump the query to inspect it

            // Conditionally apply filter based on latestPayoutDate
            if ($latestPayoutDate instanceof Carbon) {
                $totalAmountQuery->where('company_user_balance_deductions.created_at', '>', $latestPayoutDate);
            }
            // Get the contributing companies and their amounts
            $contributingCompanies = $totalAmountQuery->get([
                'companies.id as company_id',
                'companies.name as company_name',
                'company_user_balance_deductions.amount',
            ]);

            // Calculate the total amount
            $totalAmount = $contributingCompanies->sum('amount');

            // Determine the commission percentage based on the reseller level
            $commissionRate = 0;
            if ($this->reseller_level == 'Basic') {
                $commissionRate = $this->resellerLevelConfiguration->basic_commission ?? 0;

            } elseif ($this->reseller_level == 'Pro') {
                $commissionRate = $this->resellerLevelConfiguration->pro_commission ?? 0;
            }
            // Calculate the total commission amount
            $totalCommissionAmount = ($totalAmount * $commissionRate) / 100;
            $response_array['reseller_number'] = $this->reseller_number;
            $response_array['user_type'] = $this->user_type;
            // Set reseller_company_name and company_registration_document to null if user_type is individual
            if ($this->user_type == 'individual') {
                $response_array['reseller_company_name'] = null;
                $response_array['company_registration_document'] = null;
            } else {
                $response_array['reseller_company_name'] = $this->reseller_company_name;
                $response_array['company_registration_document'] = $this->company_registration_document ? asset($this->company_registration_document) : null;
            }
            $response_array['user_photo_id'] = $this->user_photo_id ? asset($this->user_photo_id) : null;
            $response_array['bank_details'] = $reseller_bank_det;
            $response_array['level_configuration'] = $resellerConfig;
            $response_array['payout_history'] = $reseller_pay_his;
            $response_array['comments'] = $comments;
            $response_array['reseller_level'] = $this->reseller_level;
            $response_array['reseller_balance'] = $totalCommissionAmount;
            $response_array['contributing_companies'] = $contributingCompanies->map(fn ($company): array => [
                'company_id' => $company->getAttribute('company_id'),
                'company_name' => $company->getAttribute('company_name'),
                'amount' => ($company->getAttribute('amount') * $commissionRate) / 100,
            ])->toArray();
        }
        if ($this->access_token !== '' && $this->access_token !== '0') {
            $response_array['access_token'] = $this->access_token;
        }

        if ($this->add_products && $this->type !== USER_TYPE_RESELLER) {
            $response_array['categories'] = ProductCategoryWithProductsResource::collection($product_categories);
        }

        if ($this->type === USER_TYPE_ADMIN || $this->type === USER_TYPE_ADMIN_STAFF) {
            $response_array['is_support_agent'] = $this->is_support_agent;
        }

        if (in_array($this->type, [USER_TYPE_ADMIN, USER_TYPE_ADMIN_STAFF, USER_TYPE_SUPER_ADMIN])) {
            $user = User::find($this->id);
            $response_array['new_activities_count'] = Activity::isNotSeen()
                ->when($this->type === USER_TYPE_ADMIN_STAFF, function (Builder $query, bool $isStaff) use ($user): void {
                    $query->where('assigned_to', $user->id);
                })
                ->count();

            $response_array['new_helpdesk_tickets_count'] = HelpdeskTicket::isNotSeen()
                ->when($this->type === USER_TYPE_ADMIN_STAFF, function (Builder $query, bool $isStaff) use ($user): void {
                    $query->where('assigned_to', $user->id);
                })
                ->count();
            $response_array['notifications'] = new NotificationCollection($user->notifications()->paginate(PER_PAGE_RECORDS_SHORT));
            $response_array['unread_notifications_count'] = $user->unreadNotifications()->count();
        }

        if ($this->type !== USER_TYPE_RESELLER) {
            $response_array['allow_editable_price'] = $app_config->allowEditablePrice;
            $response_array['payment_mode'] = config('anypos_payment.mode');
            $response_array['device_token'] = $this->device_token;
            $response_array['features'] = CustomFeatureResource::collection(CustomFeature::all());
        }

        return $response_array;
    }
}

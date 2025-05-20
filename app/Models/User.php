<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Override;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'app_config',
        'device_token',
        'type',
        'is_active',
        'branch_id',
        'company_id',
        'region_id',
        'is_machine_user',
        'is_support_agent',
        'can_manage_all_regions',
        'preferred_contact_time',
        'can_add_edit_product',
        'can_add_edit_customer',
        'can_add_pay_sales_invoice',
        'can_view_sales_invoice',
        'can_view_customer',
        'can_request_stock_adjustment',
        'can_refund_transaction',
        'odoo_reference_id',
        'allow_discount',
        'can_see_transactions',
        'is_waiter',
        'can_request_stock_transfer',
        'can_approve_stock_transfer',
        'user_type',
        'reseller_company_name',
        'company_registration_document',
        'user_photo_id',
        'reseller_number',
        'reseller_level',
        'status',
        'rejection_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getLogoAttribute(string $value): string
    {
        return $value === '' || $value === '0' ? '' : asset(Storage::url($value));
    }

    public function getIsEmployeeAttribute(): bool
    {
        return $this->type === USER_TYPE_EMPLOYEE;
    }

    /**
     * Get the company that owns the user.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Admin staff can manage multiple companies
     *
     * @return HasMany<Company>
     */
    public function adminStaffCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'admin_staff_id', 'id');
    }

    /**
     * Get the referral campaigns.
     *
     * @return HasMany<ReferralCampaign>
     */
    public function referralCampaigns(): HasMany
    {
        return $this->hasMany(ReferralCampaign::class, 'referral_id', 'id');
    }

    /**
     * Get the reseller comments.
     *
     * @return HasMany<ResellerComment>
     */
    public function Comments(): HasMany
    {
        return $this->hasMany(ResellerComment::class, 'reseller_id', 'id');
    }

    /**
     * Get the reseller bank details.
     *
     * @return HasOne<ResellerBankDetail>
     */
    public function resellerBankDetails(): HasOne
    {
        return $this->hasOne(ResellerBankDetail::class, 'reseller_id', 'id');
    }

    /**
     * Get the reseller level configuration.
     *
     * @return HasOne<ResellerLevelConfiguration>
     */
    public function resellerLevelConfiguration(): HasOne
    {
        return $this->hasOne(ResellerLevelConfiguration::class, 'reseller_id', 'id');
    }

    /**
     * Get the reseller payout history.
     *
     * @return HasMany<ResellerPayoutHistory>
     */
    public function resellerPayoutHistory(): HasMany
    {
        return $this->hasMany(ResellerPayoutHistory::class, 'reseller_id', 'id');
    }

    /**
     * Get the branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get products.
     *
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the product categories.
     *
     * @return HasMany<ProductCategory>
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /**
     * Get the product units.
     *
     * @return HasMany<ProductUnit>
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Get the transactions.
     *
     * @return HasMany<Transaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the region.
     *
     * @return BelongsTo<Region, self>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get regions of users of type staff.
     *
     * @return BelongsToMany<City>
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class)
            ->whereNull('city_user.deleted_at')
            ->withTimestamps()
            ->withPivot(['city_user.deleted_at']);
    }

    /**
     * Get the balance deductions for the user.
     *
     * @return HasMany<CompanyUserBalanceDeduction>
     */
    public function balanceDeductions(): HasMany
    {
        return $this->hasMany(CompanyUserBalanceDeduction::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->type === USER_TYPE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->type === USER_TYPE_ADMIN;
    }

    public function isAdminStaff(): bool
    {
        return $this->type === USER_TYPE_ADMIN_STAFF;
    }

    public function isAgentForCompany(Company $company): bool
    {
        return $this->isAdminStaff() && $this->id === $company->admin_staff_id;
    }

    /**
     * Specifies the user's FCM token
     */
    public function routeNotificationForFcm(): ?string
    {
        return $this->device_token;
    }

    /** @return array<mixed> */
    public function forOdoo(): array
    {
        // Get Last Subscription Ending Date
        $last_subscription = $this->company->subscriptions()->latest()->first();
        if ($last_subscription) {
            $subscription_start_date = Carbon::parse($last_subscription->start_date)->toDateString();
            $subscription_end_date = Carbon::parse($last_subscription->end_date)->toDateString();
        } else {
            $subscription_start_date = Carbon::now()->subDays(1)->toDateString();
            $subscription_end_date = Carbon::now()->subDays(1)->toDateString();
        }

        return [
            'ref' => $this->id,
            'name' => $this->name,
            'display_name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'vat' => $this->vat ?? '',
            'cr' => $this->cr ?? '',
            'contact_address' => $this->address ?? '',
            'company_name' => $this->shop_name ?? '',
            'business_type' => $this->business_type ?? '',
            'subscription_start_date' => $subscription_start_date,
            'subscription_end_date' => $subscription_end_date,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

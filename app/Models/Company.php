<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;

class Company extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (Company $company): void {
            $company->users()->each(function (User $user): void {
                $user->delete();
            });
            $company->products()->each(function (Product $product): void {
                $product->delete();
            });
            $company->productCategories()->each(function (ProductCategory $productCategory): void {
                $productCategory->delete();
            });
            $company->productUnits()->each(function (ProductUnit $productUnit): void {
                $productUnit->delete();
            });
            $company->transactions()->each(function (Transaction $transaction): void {
                $transaction->delete();
            });
            $company->branches()->each(function (Branch $branch): void {
                $branch->delete();
            });
            $company->subscriptions()->each(function (Subscription $subscription): void {
                $subscription->delete();
            });
            $company->devices()->each(function (Device $device): void {
                $device->delete();
            });
            $company->invoices()->each(function (Invoice $invoice): void {
                $invoice->delete();
            });
        });
    }

    // Generate company code which is used in transactions
    public function generateCode(): void
    {
        if (! $this->code) {
            $this->code = Str::upper(Str::substr(str_replace(' ', '', $this->name), 0, 3));
            $this->save();
        }
    }

    /**
     * Get the owner.
     *
     * @return HasOne<User>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)
            ->where('type', USER_TYPE_BUSINESS_OWNER);
    }

    /**
     * Get the users.
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the branches.
     *
     * @return HasMany<Branch>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the employees.
     *
     * @return HasMany<User>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the active employees.
     *
     * @return HasMany<User>
     */
    public function activeEmployees(): HasMany
    {
        return $this->employees()->where('is_active', BOOLEAN_TRUE);
    }

    /**
     * Only those employees who are machine users i.e. using device
     *
     * @return HasMany<User>
     */
    public function machineUserEmployees(): HasMany
    {
        return $this->employees()->where('is_machine_user', BOOLEAN_TRUE);
    }

    /**
     * Get the subscriptions.
     *
     * @return HasMany<Subscription>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the devices associated with the user.
     *
     * @return HasMany<Device>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function getActiveSubscriptionAttribute(): ?Subscription
    {
        $active_subscription = null;
        $subscriptions = $this->subscriptions()->latest()->get();
        foreach ($subscriptions as $subscription) {
            if ($subscription->start_date && $subscription->end_date) {
                $start_date = Carbon::createFromFormat('Y-m-d', $subscription->start_date);
                throw_if($start_date == false, new Exception("Invalid date format: {$subscription->start_date}"));
                $start_date = $start_date->startOfDay();

                $end_date = Carbon::createFromFormat('Y-m-d', $subscription->end_date);
                throw_if($end_date == false, new Exception("Invalid date format: {$subscription->end_date}"));
                $end_date = $end_date->endOfDay();

                if (
                    Carbon::now()->between($start_date, $end_date)
                    && ($subscription->invoice && $subscription->invoice->status === INVOICE_STATUS_PAID
                        || $subscription->is_trial === BOOLEAN_TRUE)
                ) {
                    $active_subscription = $subscription;
                    break;
                }
            }
            // for daily, end date maybe null
            if ($subscription->type === PLAN_TYPE_DAILY && ! is_null($subscription->start_date) && is_null($subscription->end_date)) {
                $active_subscription = $subscription;
                break;
            }
        }

        return $active_subscription;
    }

    public function getRequestedSubscriptionAttribute(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', BOOLEAN_FALSE)
            ->latest()
            ->first();
    }

    public function hasAvailedYearlyTrial(): bool
    {
        return $this->subscriptions()
            ->where('period', PERIOD_YEARLY)
            ->where('is_trial', BOOLEAN_TRUE)
            ->where('validity_days', 365)
            ->count()
            > 0;
    }

    /**
     * Get the invoices.
     *
     * @return HasMany<Invoice>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
     * Get the billing state.
     *
     * @return BelongsTo<Region, self>
     */
    public function billingState(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'billing_state', 'id');
    }

    /**
     * Get the city.
     *
     * @return BelongsTo<City, self>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'billing_city', 'id');
    }

    /**
     * A company can be managed by an admin staff.
     *
     * @return BelongsTo<User, self>
     */
    public function adminStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_staff_id', 'id');
    }

    /**
     * Get the products.
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
     * Get the transactions for the user.
     *
     * @return HasMany<Transaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the business type verification.
     *
     * @return BelongsTo<BusinessTypeVerification, self>
     */
    public function businessTypeVerification(): BelongsTo
    {
        return $this->belongsTo(BusinessTypeVerification::class);
    }

    /**
     * Get the activities associated with the user.
     *
     * @return HasMany<Activity>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get the notes associated with the user.
     *
     * @return HasMany<Note>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Get the crm logs.
     *
     * @return HasMany<CrmLog>
     */
    public function crmLogs(): HasMany
    {
        return $this->hasMany(CrmLog::class);
    }

    /**
     * Get the discounts.
     *
     * @return HasMany<Discount>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    /**
     * Get the external integrations.
     *
     * @return HasMany<ExternalIntegration>
     */
    public function externalIntegrations(): HasMany
    {
        return $this->hasMany(ExternalIntegration::class);
    }

    /**
     * Get the questionnaire.
     *
     * @return HasOne<Questionnaire>
     */
    public function questionnaire(): HasOne
    {
        return $this->hasOne(Questionnaire::class);
    }

    /**
     * Get the balance.
     *
     * @return HasOne<Balance>
     */
    public function balance(): HasOne
    {
        return $this->hasOne(Balance::class);
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
 
    /**
     * Get the company addons.
     *
     * @return HasMany<CompanyAddon>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(CompanyAddon::class, 'company_id', 'id');
    }

    /**
     * Get the active addons.
     *
     * @return HasMany<CompanyAddon>
     */
    public function activeAddons(): HasMany
    {
        return $this->addons()
            ->where('status', true)
            ->whereNotNull('start_date')
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Check if the addon is in trial and if the trial has ended.
     */
    public function isAddonInTrial(CompanyAddon $addon): bool
    {
        if ($addon->trial_validity_days > 0) {
            $trialEndDate = Carbon::parse($addon->trial_started_at)->addDays($addon->trial_validity_days);
            return Carbon::now()->lessThanOrEqualTo($trialEndDate);
        }

        return false;
    }

    public function createStocksForBranchesIfNotExist(): void
    {
        $allProductIds = $this->products()->where('is_stockable', BOOLEAN_TRUE)->pluck('id');
        $userId = auth()->id();

        foreach ($this->branches as $branch) {
            $missingProductIds = $allProductIds->diff($branch->stocks->pluck('product_id'));

            $stocksToCreate = $missingProductIds->map(fn ($productId): array => ['product_id' => $productId, 'quantity' => 0, 'created_by' => $userId])->all();

            $branch->stocks()->createMany($stocksToCreate);
        }
    }

    /**
     * constraint companies with unpaid invoices which are generated days ago
     *
     * @param Builder<Company> $query
     */
    public function scopeWithUnpaidInvoices(Builder $query, int $daysOlder): void
    {
        $query->whereHas('invoices', function ($query) use ($daysOlder): void {
            $query->where('status', INVOICE_STATUS_UNPAID);
            $query->where('created_at', '<', now()->subDays($daysOlder));
        });
    }

    /**
     * constraint companies for incomplete profiles which are generated days ago
     *
     * @param Builder<Company> $query
     */
    public function scopeIncompleteProfiles(Builder $query, int $daysOlder): void
    {
        $query->where('status', COMPANY_STATUS_KYC)->where('created_at', '<', now()->subDays($daysOlder));
    }

    public function isInvoiceUnpaidForDays(int $days): bool
    {
        return $this->invoices()->where('invoices.status', INVOICE_STATUS_UNPAID)
            ->where('created_at', '<', now()->subDays($days))->count() > 0;
    }

    /**
     * absent on odoo scope
     *
     * @param Builder<Company> $query
     */
    public function scopeAbsentOnOdoo(Builder $query): void
    {
        $query->where('status', COMPANY_STATUS_ACTIVE)->where('created_on_odoo', false)->where('updated_at', '<', now()->subMinutes(5));
    }

    /**
     * admin staff is user scope
     *
     * @param Builder<Company> $query
     */
    public function scopeAdminStaffIsAuthUser(Builder $query): void
    {
        $query->where('admin_staff_id', auth()->id());
    }

    public function hasOdooIntegration(): bool
    {
        return $this->externalIntegrationQueryFor('Odoo')->exists();
    }

    public function hasXeroIntegration(): bool
    {
        return $this->externalIntegrationQueryFor('Xero')->exists();
    }

    public function hasZohoIntegration(): bool
    {
        return $this->externalIntegrationQueryFor('Zoho')->exists();
    }

    public function OdooIntegration(): ?Model
    {
        return $this->externalIntegrationQueryFor('Odoo')->first();
    }

    public function XeroIntegration(): ?Model
    {
        return $this->externalIntegrationQueryFor('Xero')->first();
    }

    public function ZohoIntegration(): ?Model
    {
        return $this->externalIntegrationQueryFor('Zoho')->first();
    }

    /**
     * @return HasMany<ExternalIntegration>
     */
    public function externalIntegrationQueryFor(string $type): HasMany
    {
        return $this->externalIntegrations()->whereHas('externalIntegrationType', function (Builder $query) use ($type): void {
            $query->where('name', $type);
        });
    }
    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts() : array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

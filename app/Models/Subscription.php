<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Subscription extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'is_trial',
        'start_date',
        'end_date',
    ];

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (Subscription $subscription): void {
            $subscription->userLicenses()->each(function (SubscriptionUserLicense $subscriptionUserLicense): void {
                $subscriptionUserLicense->delete();
            });
        });
    }

    /**
     * Get the Company.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the invoice.
     *
     * @return HasOne<Invoice>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)
            ->where('type', INVOICE_TYPE_SUBSCRIPTION);
    }

    /**
     * Get the user licences.
     *
     * @return HasMany<SubscriptionUserLicense>
     */
    public function userLicenses(): HasMany
    {
        return $this->hasMany(SubscriptionUserLicense::class);
    }

    /**
     * subscriptions expired yesterday scope
     *
     * @param Builder<Subscription> $query
     */
    public function scopeExpiredYesterday(Builder $query): void
    {
        $query->where('end_date', Carbon::yesterday()->toDateString());
    }

    public function isYearlyTrial(): bool
    {
        return $this->period == PERIOD_YEARLY &&
        $this->is_trial == BOOLEAN_TRUE &&
        $this->validity_days == 365;
    }

    public function getUserLicensesCountAttribute(): int
    {
        return self::calculateUserLicensesCount();
    }

    public function getUserLicensesCountAllAttribute(): int
    {
        return self::calculateUserLicensesCount(false);
    }

    protected function calculateUserLicensesCount(bool $paid_only = true): int
    {
        if ($this->userLicenses()->count() == 0) {
            $user_licenses_count = $this->type === PLAN_TYPE_PRO || $this->type === PLAN_TYPE_DAILY ? 1 : 0;
        } else {
            $count = 0;
            foreach ($this->userLicenses as $license) {
                // Only Count License if it's status is TRUE,
                // Which means they are paid
                if (! $paid_only || $license->status === BOOLEAN_TRUE) {
                    $count += $license->quantity;
                }
            }
            $user_licenses_count = $count;
        }

        return $user_licenses_count;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

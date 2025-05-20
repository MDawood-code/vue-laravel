<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionUserLicense extends Model
{
    use SoftDeletes, LogsActivity;

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
     * Get the Subscription.
     *
     * @return BelongsTo<Subscription, self>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * starting today scope
     *
     * @param Builder<SubscriptionUserLicense> $query
     */
    public function scopeStartingToday(Builder $query): void
    {
        $query->whereDate('start_date', now());
    }

    /**
     * user licenses that are not daily scope
     *
     * @param Builder<SubscriptionUserLicense> $query
     */
    public function scopeNotDaily(Builder $query): void
    {
        $query->whereDoesntHave('subscription', function (Builder $query): void {
            $query->where('type', '=', PLAN_TYPE_DAILY);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

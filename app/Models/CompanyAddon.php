<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAddon extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'company_addons';

    protected $fillable = [
        'company_id',
        'addon_id',
        'price',
        'discount',
        'start_date',
        'end_date',
        'status',
        'trial_validity_days',
        'trial_started_at',
        'trial_ended_at',
    ];

    /**
     * Get the company.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the Addon.
     *
     * @return BelongsTo<Addon, self>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * had trial scope
     *
     * @param Builder<CompanyAddon> $query
     */
    public function scopeHasTrial(Builder $query): void
    {
        $query->where('trial_ended_at', '>=', now()->toDateString());
    }

    /**
     * has no trial scope
     *
     * @param Builder<CompanyAddon> $query
     */
    public function scopeHasNoTrial(Builder $query): void
    {
        $query->whereNull('trial_ended_at')->orWhere('trial_ended_at', '<', now()->toDateString());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

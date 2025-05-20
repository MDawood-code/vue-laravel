<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'image',
        'icon',
        'price',
        'discount',
        'billing_cycle',
        'trial_validity_days',
    ];

    /**
     * Get the company addons subscribed.
     *
     * @return HasMany<CompanyAddon>
     */
    public function companyAddons(): HasMany
    {
        return $this->hasMany(CompanyAddon::class);
    }

    /**
     * Get the active company addons.
     *
     * @return HasMany<CompanyAddon>
     */
    public function activeCompanyAddons(): HasMany
    {
        return $this->companyAddons()
            ->where('status', true)
            ->whereNotNull('start_date')
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            });
    }

    /**
     * Get the dependent addons.
     *
     * @return BelongsToMany<Addon>
     */
    public function dependentAddons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_dependencies', 'addon_id', 'dependent_addon_id');
    }

    /**
     * Get the required by addons.
     *
     * @return BelongsToMany<Addon>
     */
    public function requiredByAddons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'addon_dependencies', 'dependent_addon_id', 'addon_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }

    

}

<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $guarded = [];

    /**
     * Get the users of a region.
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->whereNull('city_user.deleted_at')
            ->withTimestamps()
            ->withPivot(['city_user.deleted_at']);
    }

    /**
     * Get the companies.
     *
     * @return HasMany<Company>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
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

    public function belongsToRegion(Region $region): bool
    {
        if ($this->region->id === $region->id) {
            return true;
        } else {
            return false;
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

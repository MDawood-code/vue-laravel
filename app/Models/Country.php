<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sushi\Sushi;

/**
 * App/Models/Country
 *
 * @property string $name_en
 * @property string $name_ar
 */
class Country extends Model
{
    use HasFactory, Sushi, LogsActivity;

    /** @var array<mixed> */
    protected $rows = [
        [
            'id' => COUNTRY_SAUDI_ARABIA,
            'name_en' => 'Saudi Arabia',
            'name_ar' => 'المملكة العربية السعودية',
        ],
    ];

    /**
     * Get the regions.
     *
     * @return HasMany<Region>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

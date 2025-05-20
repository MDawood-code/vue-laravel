<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResellerLevelConfiguration extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'reseller_level_configuration';

    protected $fillable = ['reseller_id', 'basic_commission', 'basic_retain_rate', 'basic_target', 'pro_commission', 'pro_retain_rate', 'pro_target'];

    /**
     * Get the reseller.
     *
     * @return HasOne<User>
     */
    public function reseller(): HasOne
    {
        return $this->hasOne(User::class)
            ->where('type', USER_TYPE_RESELLER);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}

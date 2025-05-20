<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResellerPayoutHistory extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'payout_history_reseller';

    protected $fillable = ['reseller_id', 'account_number', 'reference_number', 'amount', 'date'];

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
